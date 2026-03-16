<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for score book to tag linking table.
 *
 * @extends QBMapper<\OCP\AppFramework\Db\Entity>
 */
class ScoreBookTagLinkMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_scorebooks_tags', null);
	}

	/**
	 * Find all tag IDs for a given score book.
	 *
	 * @param int $scoreBookId
	 * @return int[] tag ids
	 * @throws Exception
	 */
	public function findTagIdsForScoreBook(int $scoreBookId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag_id')->from($this->getTableName())
			->where($qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId)));
		/** @var array<int, array{tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$ids = [];
		foreach ($rows as $r) {
			$ids[] = (int)$r['tag_id'];
		}
		return $ids;
	}

	/**
	 * Find tag IDs for multiple score books.
	 *
	 * @param int[] $scoreBookIds
	 * @return array<int, int[]> map of score book id to tag ids
	 * @throws Exception
	 */
	public function findTagIdsForScoreBooks(array $scoreBookIds): array {
		$scoreBookIds = array_map('intval', array_values($scoreBookIds));
		if (empty($scoreBookIds)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_book_id', 'tag_id')->from($this->getTableName())
			->where($qb->expr()->in('score_book_id', $qb->createNamedParameter($scoreBookIds, IQueryBuilder::PARAM_INT_ARRAY)));
		/** @var array<int, array{score_book_id: int|string, tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $r) {
			$scoreBookId = (int)$r['score_book_id'];
			$tagId = (int)$r['tag_id'];
			if (!isset($result[$scoreBookId])) {
				$result[$scoreBookId] = [];
			}
			$result[$scoreBookId][] = $tagId;
		}
		return $result;
	}

	/**
	 * Set tags for a score book (replace all existing).
	 *
	 * @param int $scoreBookId
	 * @param int[] $tagIds
	 * @throws Exception
	 * @throws \Throwable
	 */
	public function setTagsForScoreBook(int $scoreBookId, array $tagIds): void {
		$tagIds = array_map('intval', array_values($tagIds));
		$current = array_map('intval', array_values($this->findTagIdsForScoreBook($scoreBookId)));

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
							$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId)),
							$qb->expr()->in('tag_id', $qb->createNamedParameter($toDelete, IQueryBuilder::PARAM_INT_ARRAY))
						)
					);
				$qb->executeStatement();
			}

			// Multiple single-row inserts inside the transaction
			if (!empty($toAdd)) {
				foreach ($toAdd as $tagId) {
					$qb = $this->db->getQueryBuilder();
					$qb->insert($this->getTableName())
						->values([
							'score_book_id' => $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT),
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

	/**
	 * Delete all tag links for a score book.
	 *
	 * @param int $scoreBookId
	 * @throws Exception
	 */
	public function deleteAllTagsForScoreBook(int $scoreBookId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
