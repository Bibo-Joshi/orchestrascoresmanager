<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Db\Enum\ConductorsScoreVariantType;
use OCA\OrchestraScoresManager\Policy\ConductorsScoreVariantPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

class ConductorsScoreVariantService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ConductorsScoreVariantMapper $conductorsScoreVariantMapper,
		private readonly ConductorsScoreMapper $conductorsScoreMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly ConductorsScoreVariantPolicy $conductorsScoreVariantPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all variants of a conductor's score.
	 *
	 * @param int $conductorsScoreId
	 * @return array<ConductorsScoreVariant>
	 * @throws InvalidArgumentException if the conductor's score does not exist
	 * @throws Exception
	 */
	public function getVariantsForConductorsScore(int $conductorsScoreId): array {
		$this->authorizationService->authorizePolicy($this->conductorsScoreVariantPolicy, PolicyInterface::ACTION_READ);
		$this->assertConductorsScoreExists($conductorsScoreId);
		return $this->conductorsScoreVariantMapper->findByConductorsScoreId($conductorsScoreId);
	}

	/**
	 * Find a conductor's score variant entity by its ID.
	 *
	 * @param int $id
	 * @return ConductorsScoreVariant
	 * @throws InvalidArgumentException if the variant does not exist
	 * @throws Exception
	 */
	public function findConductorsScoreVariantEntity(int $id): ConductorsScoreVariant {
		$this->authorizationService->authorizePolicy($this->conductorsScoreVariantPolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->conductorsScoreVariantMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Conductor\'s score variant not found'));
		}
	}

	/**
	 * Get a serialized conductor's score variant by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the variant does not exist
	 * @throws Exception
	 */
	public function getConductorsScoreVariantById(int $id): array {
		return $this->findConductorsScoreVariantEntity($id)->jsonSerialize();
	}

	/**
	 * Create a variant of a conductor's score.
	 *
	 * @param int $conductorsScoreId
	 * @param string $type One of the values of ConductorsScoreVariantType
	 * @param string|null $description
	 * @return ConductorsScoreVariant
	 * @throws InvalidArgumentException if the conductor's score does not exist or the parameters are invalid
	 * @throws Exception
	 */
	public function createConductorsScoreVariant(int $conductorsScoreId, string $type, ?string $description = null): ConductorsScoreVariant {
		$this->authorizationService->authorizePolicy($this->conductorsScoreVariantPolicy, PolicyInterface::ACTION_CREATE);

		$type = $this->validateType($type);
		$description = $this->normalize($description);
		$this->assertConductorsScoreExists($conductorsScoreId);

		return $this->conductorsScoreVariantMapper->insert(ConductorsScoreVariant::fromParams([
			'conductorsScoreId' => $conductorsScoreId,
			'type' => $type,
			'description' => $description,
		]));
	}

	/**
	 * Update a variant. Parameters that are null are not changed.
	 * The conductor's score of a variant cannot be changed.
	 *
	 * @param ConductorsScoreVariant $variant
	 * @param string|null $type
	 * @param string|null $description An empty string removes the description
	 * @return array The complete serialized variant
	 * @throws InvalidArgumentException if the parameters are invalid
	 * @throws Exception
	 */
	public function updateConductorsScoreVariant(ConductorsScoreVariant $variant, ?string $type = null, ?string $description = null): array {
		$this->authorizationService->authorizePolicy($this->conductorsScoreVariantPolicy, PolicyInterface::ACTION_UPDATE, $variant);

		if ($type !== null) {
			$variant->setType($this->validateType($type));
		}
		if ($description !== null) {
			$variant->setDescription($this->normalize($description));
		}
		return $this->conductorsScoreVariantMapper->update($variant)->jsonSerialize();
	}

	/**
	 * Delete a variant of a conductor's score.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the variant does not exist
	 * @throws Exception
	 */
	public function deleteConductorsScoreVariant(int $id): void {
		$this->authorizationService->authorizePolicy($this->conductorsScoreVariantPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$variant = $this->conductorsScoreVariantMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Conductor\'s score variant not found'));
		}
		$this->conductorsScoreVariantMapper->delete($variant);
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	private function assertConductorsScoreExists(int $conductorsScoreId): void {
		try {
			$this->conductorsScoreMapper->find($conductorsScoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Conductor\'s score not found'));
		}
	}

	/**
	 * Trim the type and validate that it is supported.
	 *
	 * @throws InvalidArgumentException
	 */
	private function validateType(string $type): string {
		$type = trim($type);
		if (ConductorsScoreVariantType::tryFrom($type) === null) {
			throw new InvalidArgumentException($this->l->t('Unsupported conductor\'s score variant type "%s"', [$type]));
		}
		return $type;
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
