<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\ConductorsScorePolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IL10N;
use Throwable;

class ConductorsScoreService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ConductorsScoreMapper $conductorsScoreMapper,
		private readonly ConductorsScoreVariantMapper $conductorsScoreVariantMapper,
		private readonly ScoreMapper $scoreMapper,
		private readonly IDBConnection $db,
		private readonly AuthorizationService $authorizationService,
		private readonly ConductorsScorePolicy $conductorsScorePolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get the conductor's score of a score, if it has one.
	 *
	 * @param int $scoreId
	 * @return ConductorsScore|null
	 * @throws InvalidArgumentException if the score does not exist
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getConductorsScoreForScore(int $scoreId): ?ConductorsScore {
		$this->authorizationService->authorizePolicy($this->conductorsScorePolicy, PolicyInterface::ACTION_READ);
		$this->assertScoreExists($scoreId);
		return $this->conductorsScoreMapper->findByScoreId($scoreId);
	}

	/**
	 * Find a conductor's score entity by its ID.
	 *
	 * @param int $id
	 * @return ConductorsScore
	 * @throws InvalidArgumentException if the conductor's score does not exist
	 * @throws Exception
	 */
	public function findConductorsScoreEntity(int $id): ConductorsScore {
		$this->authorizationService->authorizePolicy($this->conductorsScorePolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->conductorsScoreMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Conductor\'s score not found'));
		}
	}

	/**
	 * Get a serialized conductor's score by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the conductor's score does not exist
	 * @throws Exception
	 */
	public function getConductorsScoreById(int $id): array {
		return $this->findConductorsScoreEntity($id)->jsonSerialize();
	}

	/**
	 * Create the conductor's score of a score.
	 *
	 * @param int $scoreId
	 * @return ConductorsScore
	 * @throws InvalidArgumentException if the score does not exist or already has a conductor's score
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function createConductorsScore(int $scoreId): ConductorsScore {
		$this->authorizationService->authorizePolicy($this->conductorsScorePolicy, PolicyInterface::ACTION_CREATE);

		$this->assertScoreExists($scoreId);
		// The unique index is the backstop, this gives a friendly error
		if ($this->conductorsScoreMapper->findByScoreId($scoreId) !== null) {
			throw new InvalidArgumentException($this->l->t('The score already has a conductor\'s score'));
		}

		return $this->conductorsScoreMapper->insert(ConductorsScore::fromParams(['scoreId' => $scoreId]));
	}

	/**
	 * Delete a conductor's score together with its variants.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the conductor's score does not exist
	 * @throws Exception
	 * @throws Throwable
	 */
	public function deleteConductorsScore(int $id): void {
		$this->authorizationService->authorizePolicy($this->conductorsScorePolicy, PolicyInterface::ACTION_DELETE);
		try {
			$conductorsScore = $this->conductorsScoreMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Conductor\'s score not found'));
		}

		$this->db->beginTransaction();
		try {
			// Delete variants first (cascade should handle this, but let's be sure)
			$this->conductorsScoreVariantMapper->deleteByConductorsScoreId($id);
			$this->conductorsScoreMapper->delete($conductorsScore);
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
}
