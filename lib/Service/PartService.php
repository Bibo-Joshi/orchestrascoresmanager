<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\PartPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IL10N;
use Throwable;

class PartService {
	private const MAX_TITLE_LENGTH = 160;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PartMapper $partMapper,
		private readonly PartVariantMapper $partVariantMapper,
		private readonly ScoreMapper $scoreMapper,
		private readonly IDBConnection $db,
		private readonly AuthorizationService $authorizationService,
		private readonly PartPolicy $partPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all parts of a score.
	 *
	 * @param int $scoreId
	 * @return array<Part>
	 * @throws InvalidArgumentException if the score does not exist
	 * @throws Exception
	 */
	public function getPartsForScore(int $scoreId): array {
		$this->authorizationService->authorizePolicy($this->partPolicy, PolicyInterface::ACTION_READ);
		$this->assertScoreExists($scoreId);
		return $this->partMapper->findByScoreId($scoreId);
	}

	/**
	 * Find a part entity by its ID.
	 *
	 * @param int $id
	 * @return Part
	 * @throws InvalidArgumentException if the part does not exist
	 * @throws Exception
	 */
	public function findPartEntity(int $id): Part {
		$this->authorizationService->authorizePolicy($this->partPolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->partMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Part not found'));
		}
	}

	/**
	 * Get a serialized part by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the part does not exist
	 * @throws Exception
	 */
	public function getPartById(int $id): array {
		return $this->findPartEntity($id)->jsonSerialize();
	}

	/**
	 * Create a part for a score.
	 *
	 * @param int $scoreId
	 * @param string $title
	 * @param string|null $description
	 * @return Part
	 * @throws InvalidArgumentException if the score does not exist or the parameters are invalid
	 * @throws Exception
	 */
	public function createPart(int $scoreId, string $title, ?string $description = null): Part {
		$this->authorizationService->authorizePolicy($this->partPolicy, PolicyInterface::ACTION_CREATE);

		$title = $this->normalizeTitle($title);
		$description = $this->normalize($description);
		$this->assertScoreExists($scoreId);

		return $this->partMapper->insert(Part::fromParams([
			'scoreId' => $scoreId,
			'title' => $title,
			'description' => $description,
		]));
	}

	/**
	 * Update a part. Parameters that are null are not changed. The score of a part cannot be changed.
	 *
	 * @param Part $part
	 * @param string|null $title
	 * @param string|null $description An empty string removes the description
	 * @return array The complete serialized part
	 * @throws InvalidArgumentException if the parameters are invalid
	 * @throws Exception
	 */
	public function updatePart(Part $part, ?string $title = null, ?string $description = null): array {
		$this->authorizationService->authorizePolicy($this->partPolicy, PolicyInterface::ACTION_UPDATE, $part);

		if ($title !== null) {
			$part->setTitle($this->normalizeTitle($title));
		}
		if ($description !== null) {
			$part->setDescription($this->normalize($description));
		}
		return $this->partMapper->update($part)->jsonSerialize();
	}

	/**
	 * Delete a part together with its variants. Clefs and notation presets are not touched.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the part does not exist
	 * @throws Exception
	 * @throws Throwable
	 */
	public function deletePart(int $id): void {
		$this->authorizationService->authorizePolicy($this->partPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$part = $this->partMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Part not found'));
		}

		$this->db->beginTransaction();
		try {
			// Delete variants first (cascade should handle this, but let's be sure)
			$this->partVariantMapper->deleteByPartId($id);
			$this->partMapper->delete($part);
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
	private function assertScoreExists(int $scoreId): void {
		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Score not found'));
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
