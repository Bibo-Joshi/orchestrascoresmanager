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
 * @extends QBMapper<Score>
 */
class ScoreMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		IDBConnection $db,
		protected readonly ScoreTagLinkMapper $scoreTagLinkMapper,
		protected readonly TagMapper $tagMapper,
		protected readonly ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper,
	) {
		parent::__construct($db, 'osm_scores', Score::class);
	}

	/**
	 * Find a score by ID and attach transient properties.
	 *
	 * @param int $id
	 * @return Score
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): Score {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$score = $this->findEntity($qb);
		$this->attachTags($score);
		$this->attachScoreBookInfo($score);
		return $score;
	}

	/**
	 * Get all scores with tags and score book info attached.
	 *
	 * @return array<Score> Scores
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName());
		$scores = $this->findEntities($qb);

		$scoreIds = array_map(fn (Score $s) => $s->getId(), $scores);

		// Attach tags to each score
		// We don't use attachTags in a loop to avoid N+1 DB queries
		$scoreTagIdsMap = $this->scoreTagLinkMapper->findTagIdsForScores($scoreIds);

		// get a merged list of all tag IDs
		$allTagIds = [];
		foreach ($scoreTagIdsMap as $tagIds) {
			$allTagIds = array_merge($allTagIds, $tagIds);
		}
		$allTagIds = array_values(array_unique($allTagIds));

		// Next, get the mapping of tag IDs to their name
		$tagIdNameMap = [];
		foreach ($this->tagMapper->findMultiple($allTagIds) as $tag) {
			$tagIdNameMap[$tag->getId()] = $tag->getName();
		}

		// Get score book info for all scores
		$scoreBookInfoMap = $this->scoreBookScoreLinkMapper->findScoreBooksForScores($scoreIds);

		// Finally, attach the tag names and score book info to each score
		foreach ($scores as $score) {
			$tagNames = [];
			$tagIds = $scoreTagIdsMap[$score->getId()] ?? [];
			foreach ($tagIds as $tagId) {
				if (isset($tagIdNameMap[$tagId])) {
					$tagNames[] = $tagIdNameMap[$tagId];
				}
			}
			$score->setTags($tagNames);

			// Attach score book info as object
			$bookInfo = $scoreBookInfoMap[$score->getId()] ?? null;
			if ($bookInfo !== null) {
				$score->setScoreBook([
					'id' => $bookInfo['score_book_id'],
					'index' => $bookInfo['index'],
				]);
			}
		}

		return $scores;
	}

	/**
	 * Find scores by multiple IDs.
	 *
	 * @param int[] $ids
	 * @return Score[]
	 * @throws Exception
	 */
	public function findMultiple(array $ids): array {
		$ids = array_map('intval', array_values($ids));
		if (empty($ids)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$scores = $this->findEntities($qb);

		// Attach tags and score book info (similar to findAll)
		$scoreIds = array_map(fn (Score $s) => $s->getId(), $scores);
		$scoreTagIdsMap = $this->scoreTagLinkMapper->findTagIdsForScores($scoreIds);
		$allTagIds = [];
		foreach ($scoreTagIdsMap as $tagIds) {
			$allTagIds = array_merge($allTagIds, $tagIds);
		}
		$allTagIds = array_values(array_unique($allTagIds));
		$tagIdNameMap = [];
		foreach ($this->tagMapper->findMultiple($allTagIds) as $tag) {
			$tagIdNameMap[$tag->getId()] = $tag->getName();
		}
		$scoreBookInfoMap = $this->scoreBookScoreLinkMapper->findScoreBooksForScores($scoreIds);

		foreach ($scores as $score) {
			$tagNames = [];
			$tagIds = $scoreTagIdsMap[$score->getId()] ?? [];
			foreach ($tagIds as $tagId) {
				if (isset($tagIdNameMap[$tagId])) {
					$tagNames[] = $tagIdNameMap[$tagId];
				}
			}
			$score->setTags($tagNames);

			$bookInfo = $scoreBookInfoMap[$score->getId()] ?? null;
			if ($bookInfo !== null) {
				$score->setScoreBook([
					'id' => $bookInfo['score_book_id'],
					'index' => $bookInfo['index'],
				]);
			}
		}

		return $scores;
	}

	/**
	 * Attach tags to a single score.
	 *
	 * @param Score $score
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function attachTags(Score $score): void {
		$ids = $this->scoreTagLinkMapper->findTagIdsForScore($score->getId());
		$names = [];
		foreach ($ids as $id) {
			$tag = $this->tagMapper->find($id);
			$names[] = $tag->getName();
		}
		$score->setTags($names);
	}

	/**
	 * Attach score book info to a single score.
	 *
	 * @param Score $score
	 * @throws Exception
	 */
	private function attachScoreBookInfo(Score $score): void {
		$bookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($score->getId());
		if ($bookInfo !== null) {
			$score->setScoreBook([
				'id' => $bookInfo['score_book_id'],
				'index' => $bookInfo['index'],
			]);
		}
	}
}
