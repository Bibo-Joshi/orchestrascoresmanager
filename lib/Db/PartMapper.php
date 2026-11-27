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
 * @extends QBMapper<Part>
 */
class PartMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_parts', Part::class);
	}

	/**
	 * Find a part by its ID.
	 *
	 * @param int $id
	 * @return Part
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): Part {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all parts of a given score.
	 *
	 * @param int $scoreId
	 * @return array<Part>
	 * @throws Exception
	 */
	public function findByScoreId(int $scoreId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Delete all parts of a given score.
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
