<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for score to tag linking table.
 *
 * @extends QBMapper<\OCP\AppFramework\Db\Entity>
 */
class ScoreTagLinkMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_scores_tags', null);
	}

	/**
	 * @param int $scoreId
	 * @return int[] tag ids
	 * @throws Exception
	 */
	public function findTagIdsForScore(int $scoreId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag_id')->from($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId)));
		/** @var array<int, array{tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$ids = [];
		foreach ($rows as $r) {
			$ids[] = (int)$r['tag_id'];
		}
		return $ids;
	}

	/**
	 * @param int[] $scoreIds
	 * @return array<int, int[]> map of score id to tag ids
	 * @throws Exception
	 */
	public function findTagIdsForScores(array $scoreIds): array {
		$scoreIds = array_map('intval', array_values($scoreIds));
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id', 'tag_id')->from($this->getTableName())
			->where($qb->expr()->in('score_id', $qb->createNamedParameter($scoreIds, IQueryBuilder::PARAM_INT_ARRAY)));
		/** @var array<int, array{score_id: int|string, tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $r) {
			$scoreId = (int)$r['score_id'];
			$tagId = (int)$r['tag_id'];
			if (!isset($result[$scoreId])) {
				$result[$scoreId] = [];
			}
			$result[$scoreId][] = $tagId;
		}
		return $result;
	}

	/**
	 * Delete all tag links for a given score.
	 *
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function deleteAllTagsForScore(int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param int $scoreId
	 * @param int[] $tagIds
	 * @throws Exception
	 * @throws \Throwable
	 */
	public function setTagsForScore(int $scoreId, array $tagIds): void {
		$tagIds = array_map('intval', array_values($tagIds));
		$current = array_map('intval', array_values($this->findTagIdsForScore($scoreId)));

		$toDelete = array_values(array_diff($current, $tagIds));
		$toAdd = array_values(array_diff($tagIds, $current));

		if (empty($toDelete) && empty($toAdd)) {
			return;
		}

		// Use transaction to reduce contention and ensure atomicity
		$this->db->beginTransaction();
		try {
			// Batch delete removed tags
			if (!empty($toDelete)) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete($this->getTableName())
					->where(
						$qb->expr()->andX(
							$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId)),
							$qb->expr()->in('tag_id', $qb->createNamedParameter($toDelete, IQueryBuilder::PARAM_INT_ARRAY))
						)
					);
				$qb->executeStatement();
			}

			// Multiple single-row inserts inside the transaction
			if (!empty($toAdd)) {
				// Use a fresh QueryBuilder per insert to keep parameters isolated
				foreach ($toAdd as $tagId) {
					$qb = $this->db->getQueryBuilder();
					$qb->insert($this->getTableName())
						->values([
							'score_id' => $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT),
							'tag_id' => $qb->createNamedParameter($tagId, IQueryBuilder::PARAM_INT),
						]);
					$qb->executeStatement();
				}
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
