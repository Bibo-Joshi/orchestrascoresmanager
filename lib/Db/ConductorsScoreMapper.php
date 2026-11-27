<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ConductorsScore>
 */
class ConductorsScoreMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_conductors_scores', ConductorsScore::class);
	}

	/**
	 * Find a conductor's score by its ID.
	 *
	 * @param int $id
	 * @return ConductorsScore
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): ConductorsScore {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find the conductor's score of a given score. A score has at most one.
	 *
	 * @param int $scoreId
	 * @return ConductorsScore|null
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByScoreId(int $scoreId): ?ConductorsScore {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Delete the conductor's score of a given score.
	 *
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function deleteByScoreId(int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
