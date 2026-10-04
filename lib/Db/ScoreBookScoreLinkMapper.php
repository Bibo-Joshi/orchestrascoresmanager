<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for score book to score linking table.
 * Handles the relationship between score books and their contained scores with index positions.
 *
 * @extends QBMapper<\OCP\AppFramework\Db\Entity>
 */
class ScoreBookScoreLinkMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_score_book_scores', null);
	}

	/**
	 * Find all scores for a given score book.
	 *
	 * @param int $scoreBookId
	 * @return array<array{score_id: int, index: int}> Array of score info with index
	 * @throws Exception
	 */
	public function findScoresForScoreBook(int $scoreBookId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id', 'index')
			->from($this->getTableName())
			->where($qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)))
			->orderBy('index', 'ASC');
		/** @var array<int, array{score_id: int|string, index: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'score_id' => (int)$row['score_id'],
				'index' => (int)$row['index'],
			];
		}
		return $result;
	}

	/**
	 * Find the score book info for a given score.
	 *
	 * @param int $scoreId
	 * @return array{score_book_id: int, index: int}|null Score book info or null if not in a book
	 * @throws Exception
	 */
	public function findScoreBookForScore(int $scoreId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_book_id', 'index')
			->from($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		/** @var array{score_book_id: int|string, index: int|string}|false $row */
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		return [
			'score_book_id' => (int)$row['score_book_id'],
			'index' => (int)$row['index'],
		];
	}

	/**
	 * Find score book info for multiple scores.
	 *
	 * @param int[] $scoreIds
	 * @return array<int, array{score_book_id: int, index: int}> Map of score id to score book info
	 * @throws Exception
	 */
	public function findScoreBooksForScores(array $scoreIds): array {
		$scoreIds = array_map('intval', array_values($scoreIds));
		if (empty($scoreIds)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id', 'score_book_id', 'index')
			->from($this->getTableName())
			->where($qb->expr()->in('score_id', $qb->createNamedParameter($scoreIds, IQueryBuilder::PARAM_INT_ARRAY)));
		/** @var array<int, array{score_id: int|string, score_book_id: int|string, index: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$scoreId = (int)$row['score_id'];
			$result[$scoreId] = [
				'score_book_id' => (int)$row['score_book_id'],
				'index' => (int)$row['index'],
			];
		}
		return $result;
	}

	/**
	 * Add a score to a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $scoreId
	 * @param int $index
	 * @throws Exception
	 */
	public function addScoreToScoreBook(int $scoreBookId, int $scoreId, int $index): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'score_book_id' => $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT),
				'score_id' => $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT),
				'index' => $qb->createNamedParameter($index, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/**
	 * Add multiple scores to a score book.
	 *
	 * @param int $scoreBookId
	 * @param array<array{score_id: int, index: int}> $scores Array of score_id and index pairs
	 * @throws Exception
	 * @throws \Throwable
	 */
	public function addScoresToScoreBook(int $scoreBookId, array $scores): void {
		if (empty($scores)) {
			return;
		}

		$this->db->beginTransaction();
		try {
			foreach ($scores as $scoreData) {
				$qb = $this->db->getQueryBuilder();
				$qb->insert($this->getTableName())
					->values([
						'score_book_id' => $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT),
						'score_id' => $qb->createNamedParameter($scoreData['score_id'], IQueryBuilder::PARAM_INT),
						'index' => $qb->createNamedParameter($scoreData['index'], IQueryBuilder::PARAM_INT),
					]);
				$qb->executeStatement();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Remove a score from a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function removeScoreFromScoreBook(int $scoreBookId, int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT))
				)
			);
		$qb->executeStatement();
	}

	/**
	 * Remove a score from its score book (regardless of which book).
	 *
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function removeScoreFromAllBooks(int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Update the index of a score in a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $scoreId
	 * @param int $newIndex
	 * @throws Exception
	 */
	public function updateScoreIndex(int $scoreBookId, int $scoreId, int $newIndex): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('index', $qb->createNamedParameter($newIndex, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT))
				)
			);
		$qb->executeStatement();
	}

	/**
	 * Check if an index is already occupied in a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $index
	 * @return bool
	 * @throws Exception
	 */
	public function isIndexOccupied(int $scoreBookId, int $index): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('index', $qb->createNamedParameter($index, IQueryBuilder::PARAM_INT))
				)
			);
		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Count scores in a score book.
	 *
	 * @param int $scoreBookId
	 * @return int
	 * @throws Exception
	 */
	public function countScoresInScoreBook(int $scoreBookId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)));
		return (int)$qb->executeQuery()->fetchOne();
	}
}
