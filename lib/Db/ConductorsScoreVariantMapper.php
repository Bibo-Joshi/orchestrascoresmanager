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
 * @extends QBMapper<ConductorsScoreVariant>
 */
class ConductorsScoreVariantMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_conductors_score_variants', ConductorsScoreVariant::class);
	}

	/**
	 * Find a conductor's score variant by its ID.
	 *
	 * @param int $id
	 * @return ConductorsScoreVariant
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): ConductorsScoreVariant {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all variants of a given conductor's score.
	 *
	 * @param int $conductorsScoreId
	 * @return array<ConductorsScoreVariant>
	 * @throws Exception
	 */
	public function findByConductorsScoreId(int $conductorsScoreId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('conductors_score_id', $qb->createNamedParameter($conductorsScoreId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Delete all variants of a given conductor's score.
	 *
	 * @param int $conductorsScoreId
	 * @throws Exception
	 */
	public function deleteByConductorsScoreId(int $conductorsScoreId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('conductors_score_id', $qb->createNamedParameter($conductorsScoreId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Delete all variants of the conductor's score of a given score in a single statement.
	 *
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function deleteByScoreId(int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$sub = $this->db->getQueryBuilder();
		$sub->select('id')
			->from('osm_conductors_scores')
			->where($sub->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('conductors_score_id', $qb->createFunction($sub->getSQL())));
		$qb->executeStatement();
	}
}
