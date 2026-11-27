<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariant;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Policy\PartVariantPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IL10N;
use Throwable;

/**
 * Service for part variants.
 *
 * Invariant: notation_preset_id of a part variant is either NULL or references a preset whose clef and
 * transposition equal the clef and transposition of the variant. The database cannot enforce this,
 * so resolveNotation() is the single place that derives clef, transposition and preset link.
 *
 * @psalm-type ClefInput = array{id?: int, sign?: string, line?: int|null, octave?: int, title?: string|null}
 */
class PartVariantService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PartVariantMapper $partVariantMapper,
		private readonly PartMapper $partMapper,
		private readonly NotationPresetMapper $notationPresetMapper,
		private readonly ClefService $clefService,
		private readonly TranspositionValidator $transpositionValidator,
		private readonly IDBConnection $db,
		private readonly AuthorizationService $authorizationService,
		private readonly PartVariantPolicy $partVariantPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all variants of a part.
	 *
	 * @param int $partId
	 * @return array<PartVariant>
	 * @throws InvalidArgumentException if the part does not exist
	 * @throws Exception
	 */
	public function getVariantsForPart(int $partId): array {
		$this->authorizationService->authorizePolicy($this->partVariantPolicy, PolicyInterface::ACTION_READ);
		$this->assertPartExists($partId);
		return $this->partVariantMapper->findByPartId($partId);
	}

	/**
	 * Find a part variant entity by its ID.
	 *
	 * @param int $id
	 * @return PartVariant
	 * @throws InvalidArgumentException if the part variant does not exist
	 * @throws Exception
	 */
	public function findPartVariantEntity(int $id): PartVariant {
		$this->authorizationService->authorizePolicy($this->partVariantPolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->partVariantMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Part variant not found'));
		}
	}

	/**
	 * Get a serialized part variant by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the part variant does not exist
	 * @throws Exception
	 */
	public function getPartVariantById(int $id): array {
		return $this->findPartVariantEntity($id)->jsonSerialize();
	}

	/**
	 * Create a variant of a part. The notation is given either by a notation preset or by a clef and a transposition.
	 *
	 * @param int $partId
	 * @param int|null $notationPresetId The ID of an existing notation preset
	 * @param ClefInput|null $clef Either {id} of an existing clef or {sign, line, octave, title} of a clef to find or create
	 * @param array<array-key, mixed>|null $transposition {diatonic, chromatic, octave}
	 * @param string|null $description
	 * @return PartVariant
	 * @throws InvalidArgumentException if the part, preset or clef does not exist or the parameters are invalid
	 * @throws Exception
	 * @throws Throwable
	 */
	public function createPartVariant(
		int $partId,
		?int $notationPresetId = null,
		?array $clef = null,
		?array $transposition = null,
		?string $description = null,
	): PartVariant {
		$this->authorizationService->authorizePolicy($this->partVariantPolicy, PolicyInterface::ACTION_CREATE);

		$description = $this->normalize($description);
		$this->assertPartExists($partId);

		$this->db->beginTransaction();
		try {
			$notation = $this->resolveNotation($notationPresetId, $clef, $transposition, null);
			$created = $this->partVariantMapper->insert(PartVariant::fromParams([
				'partId' => $partId,
				'clefId' => $notation['clefId'],
				'transpositionDiatonic' => $notation['diatonic'],
				'transpositionChromatic' => $notation['chromatic'],
				'transpositionOctave' => $notation['octave'],
				'notationPresetId' => $notation['presetId'],
				'description' => $description,
			]));
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $created;
	}

	/**
	 * Update a part variant. Parameters that are null are not changed.
	 * The part of a variant cannot be changed.
	 *
	 * If a notation preset is given, clef and transposition are taken from it. If a clef and/or transposition is given,
	 * it is merged with the stored values, a link to a preset that no longer matches is removed and the variant is
	 * linked to the matching preset if there is exactly one. Without any of these, the notation is untouched.
	 *
	 * @param PartVariant $variant
	 * @param int|null $notationPresetId
	 * @param ClefInput|null $clef
	 * @param array<array-key, mixed>|null $transposition
	 * @param string|null $description An empty string removes the description
	 * @return array The complete serialized part variant
	 * @throws InvalidArgumentException if the preset or clef does not exist or the parameters are invalid
	 * @throws Exception
	 * @throws Throwable
	 */
	public function updatePartVariant(
		PartVariant $variant,
		?int $notationPresetId = null,
		?array $clef = null,
		?array $transposition = null,
		?string $description = null,
	): array {
		$this->authorizationService->authorizePolicy($this->partVariantPolicy, PolicyInterface::ACTION_UPDATE, $variant);

		$newDescription = $description !== null ? $this->normalize($description) : null;

		$this->db->beginTransaction();
		try {
			if ($notationPresetId !== null || $clef !== null || $transposition !== null) {
				$notation = $this->resolveNotation($notationPresetId, $clef, $transposition, $variant);
				$variant->setClefId($notation['clefId']);
				$variant->setTranspositionDiatonic($notation['diatonic']);
				$variant->setTranspositionChromatic($notation['chromatic']);
				$variant->setTranspositionOctave($notation['octave']);
				$variant->setNotationPresetId($notation['presetId']);
			}
			if ($description !== null) {
				$variant->setDescription($newDescription);
			}
			$updated = $this->partVariantMapper->update($variant);
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $updated->jsonSerialize();
	}

	/**
	 * Delete a part variant. Clefs and notation presets are not touched.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the part variant does not exist
	 * @throws Exception
	 */
	public function deletePartVariant(int $id): void {
		$this->authorizationService->authorizePolicy($this->partVariantPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$variant = $this->partVariantMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Part variant not found'));
		}
		$this->partVariantMapper->delete($variant);
	}

	/**
	 * Derive clef, transposition and preset link of a part variant from the input.
	 *
	 * Either a notation preset (form 1) or a clef and/or transposition (form 2) must be given. In form 2 on update,
	 * missing values are taken from the stored variant. The preset link is set to the stored preset if it still
	 * matches, otherwise to the only matching preset if there is exactly one, otherwise to NULL.
	 *
	 * @param int|null $notationPresetId
	 * @param ClefInput|null $clef
	 * @param array<array-key, mixed>|null $transposition
	 * @param PartVariant|null $existing The stored variant on update, null on create
	 * @return array{clefId: int, diatonic: int, chromatic: int, octave: int, presetId: int|null}
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	private function resolveNotation(?int $notationPresetId, ?array $clef, ?array $transposition, ?PartVariant $existing): array {
		if ($notationPresetId !== null) {
			if ($clef !== null || $transposition !== null) {
				throw new InvalidArgumentException($this->l->t('Provide either a notation preset or a clef with a transposition, not both'));
			}
			try {
				$preset = $this->notationPresetMapper->find($notationPresetId);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				throw new InvalidArgumentException($this->l->t('Notation preset not found'));
			}
			return [
				'clefId' => $preset->getClefId(),
				'diatonic' => $preset->getTranspositionDiatonic(),
				'chromatic' => $preset->getTranspositionChromatic(),
				'octave' => $preset->getTranspositionOctave(),
				'presetId' => $preset->getId(),
			];
		}

		if ($existing === null && ($clef === null || $transposition === null)) {
			throw new InvalidArgumentException($this->l->t('Either a notation preset or a clef with a transposition is required'));
		}

		$clefId = $clef !== null ? $this->resolveClefId($clef) : ($existing?->getClefId() ?? 0);
		$normalized = $transposition !== null
			? $this->transpositionValidator->normalize($transposition)
			: [
				'diatonic' => $existing?->getTranspositionDiatonic() ?? 0,
				'chromatic' => $existing?->getTranspositionChromatic() ?? 0,
				'octave' => $existing?->getTranspositionOctave() ?? 0,
			];

		$candidates = $this->notationPresetMapper->findByClefAndTransposition(
			$clefId,
			$normalized['diatonic'],
			$normalized['chromatic'],
			$normalized['octave'],
		);
		$candidateIds = array_map(static fn ($preset): int => $preset->getId(), $candidates);
		$storedPresetId = $existing?->getNotationPresetId();
		if ($storedPresetId !== null && in_array($storedPresetId, $candidateIds, true)) {
			$presetId = $storedPresetId;
		} elseif (count($candidateIds) === 1) {
			$presetId = $candidateIds[0];
		} else {
			$presetId = null;
		}

		return [
			'clefId' => $clefId,
			'diatonic' => $normalized['diatonic'],
			'chromatic' => $normalized['chromatic'],
			'octave' => $normalized['octave'],
			'presetId' => $presetId,
		];
	}

	/**
	 * Resolve the ID of an existing clef or find or create a clef with the given properties.
	 *
	 * @param ClefInput $clef
	 * @return int
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	private function resolveClefId(array $clef): int {
		if (isset($clef['id'])) {
			if (count($clef) > 1) {
				throw new InvalidArgumentException($this->l->t('Provide either a clef ID or the properties of a clef, not both'));
			}
			return $this->clefService->findClefEntity($clef['id'])->getId();
		}
		if (!isset($clef['sign'])) {
			throw new InvalidArgumentException($this->l->t('Either a clef ID or a clef sign is required'));
		}
		return $this->clefService->findOrCreateClef(
			$clef['sign'],
			$clef['line'] ?? null,
			$clef['octave'] ?? 0,
			$clef['title'] ?? null,
		)->getId();
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	private function assertPartExists(int $partId): void {
		try {
			$this->partMapper->find($partId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Part not found'));
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
}
