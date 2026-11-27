<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Policy\NotationPresetPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IL10N;
use Throwable;

class NotationPresetService {
	private const MAX_TITLE_LENGTH = 160;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly NotationPresetMapper $notationPresetMapper,
		private readonly PartVariantMapper $partVariantMapper,
		private readonly ClefMapper $clefMapper,
		private readonly TranspositionValidator $transpositionValidator,
		private readonly IDBConnection $db,
		private readonly AuthorizationService $authorizationService,
		private readonly NotationPresetPolicy $notationPresetPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all notation presets.
	 *
	 * @return array<NotationPreset>
	 * @throws Exception
	 */
	public function getAllNotationPresets(): array {
		$this->authorizationService->authorizePolicy($this->notationPresetPolicy, PolicyInterface::ACTION_READ);
		return $this->notationPresetMapper->findAll();
	}

	/**
	 * Find a notation preset entity by its ID.
	 *
	 * @param int $id
	 * @return NotationPreset
	 * @throws InvalidArgumentException if the notation preset does not exist
	 * @throws Exception
	 */
	public function findNotationPresetEntity(int $id): NotationPreset {
		$this->authorizationService->authorizePolicy($this->notationPresetPolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->notationPresetMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Notation preset not found'));
		}
	}

	/**
	 * Get a serialized notation preset by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the notation preset does not exist
	 * @throws Exception
	 */
	public function getNotationPresetById(int $id): array {
		return $this->findNotationPresetEntity($id)->jsonSerialize();
	}

	/**
	 * Create a notation preset.
	 *
	 * @param int $clefId The ID of an existing clef
	 * @param array<array-key, mixed> $transposition The transposition with the keys diatonic, chromatic and octave
	 * @param string $title
	 * @param string|null $description
	 * @return NotationPreset
	 * @throws InvalidArgumentException if the clef does not exist or the parameters are invalid
	 * @throws Exception
	 */
	public function createNotationPreset(int $clefId, array $transposition, string $title, ?string $description = null): NotationPreset {
		$this->authorizationService->authorizePolicy($this->notationPresetPolicy, PolicyInterface::ACTION_CREATE);

		$title = $this->normalizeTitle($title);
		$description = $this->normalize($description);
		$normalizedTransposition = $this->transpositionValidator->normalize($transposition);
		$this->assertClefExists($clefId);

		return $this->notationPresetMapper->insert(NotationPreset::fromParams([
			'clefId' => $clefId,
			'transpositionDiatonic' => $normalizedTransposition['diatonic'],
			'transpositionChromatic' => $normalizedTransposition['chromatic'],
			'transpositionOctave' => $normalizedTransposition['octave'],
			'title' => $title,
			'description' => $description,
		]));
	}

	/**
	 * Update a notation preset. Parameters that are null are not changed.
	 * Part variants linked to the preset that no longer match its clef and transposition are unlinked.
	 *
	 * @param NotationPreset $preset
	 * @param int|null $clefId
	 * @param array<array-key, mixed>|null $transposition
	 * @param string|null $title
	 * @param string|null $description An empty string removes the description
	 * @return array The complete serialized notation preset
	 * @throws InvalidArgumentException if the parameters are invalid
	 * @throws Exception
	 * @throws Throwable
	 */
	public function updateNotationPreset(
		NotationPreset $preset,
		?int $clefId = null,
		?array $transposition = null,
		?string $title = null,
		?string $description = null,
	): array {
		$this->authorizationService->authorizePolicy($this->notationPresetPolicy, PolicyInterface::ACTION_UPDATE, $preset);

		$newTitle = $title !== null ? $this->normalizeTitle($title) : null;
		$newTransposition = $transposition !== null ? $this->transpositionValidator->normalize($transposition) : null;
		if ($clefId !== null) {
			$this->assertClefExists($clefId);
		}

		if ($newTitle !== null) {
			$preset->setTitle($newTitle);
		}
		if ($description !== null) {
			$preset->setDescription($this->normalize($description));
		}
		if ($clefId !== null) {
			$preset->setClefId($clefId);
		}
		if ($newTransposition !== null) {
			$preset->setTranspositionDiatonic($newTransposition['diatonic']);
			$preset->setTranspositionChromatic($newTransposition['chromatic']);
			$preset->setTranspositionOctave($newTransposition['octave']);
		}

		$this->db->beginTransaction();
		try {
			$updated = $this->notationPresetMapper->update($preset);
			if ($clefId !== null || $newTransposition !== null) {
				$this->partVariantMapper->unlinkMismatchingForPreset($updated);
			}
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $updated->jsonSerialize();
	}

	/**
	 * Delete a notation preset. Part variants keep their own clef and transposition and are only unlinked.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the notation preset does not exist
	 * @throws Exception
	 * @throws Throwable
	 */
	public function deleteNotationPreset(int $id): void {
		$this->authorizationService->authorizePolicy($this->notationPresetPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$preset = $this->notationPresetMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Notation preset not found'));
		}

		$this->db->beginTransaction();
		try {
			// The database sets the link to NULL on delete, but let's be sure
			$this->partVariantMapper->unlinkNotationPreset($id);
			$this->notationPresetMapper->delete($preset);
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	private function assertClefExists(int $clefId): void {
		try {
			$this->clefMapper->find($clefId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Clef not found'));
		}
	}

	/**
	 * Trim a value and convert empty strings to null.
	 */
	private function normalize(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}

	/**
	 * Trim the title and validate that it is not empty or too long.
	 *
	 * @throws InvalidArgumentException
	 */
	private function normalizeTitle(string $title): string {
		$title = trim($title);
		if ($title === '') {
			throw new InvalidArgumentException($this->l->t('The title must not be empty'));
		}
		if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
			throw new InvalidArgumentException($this->l->t('The title must not be longer than %d characters', [self::MAX_TITLE_LENGTH]));
		}
		return $title;
	}
}
