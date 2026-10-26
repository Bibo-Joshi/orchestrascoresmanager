<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ScoreAudio>
 */
class ScoreAudioMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_score_audios', ScoreAudio::class);
	}

	/**
	 * Find an audio example by its ID.
	 *
	 * @param int $id
	 * @return ScoreAudio
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): ScoreAudio {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all audio examples for a given score.
	 *
	 * @param int $scoreId
	 * @return array<ScoreAudio>
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
	 * Insert an audio example after validating its source.
	 *
	 * @param Entity $entity
	 * @return ScoreAudio
	 * @throws \InvalidArgumentException If the source is invalid
	 * @throws Exception
	 */
	public function insert(Entity $entity): Entity {
		if ($entity instanceof ScoreAudio) {
			$entity->validateSource();
		}
		return parent::insert($entity);
	}

	/**
	 * Update an audio example after validating its source.
	 *
	 * @param Entity $entity
	 * @return ScoreAudio
	 * @throws \InvalidArgumentException If the source is invalid
	 * @throws Exception
	 */
	public function update(Entity $entity): Entity {
		if ($entity instanceof ScoreAudio) {
			$entity->validateSource();
		}
		return parent::update($entity);
	}
}
