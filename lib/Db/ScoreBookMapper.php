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
 * Mapper for ScoreBook entity.
 *
 * @extends QBMapper<ScoreBook>
 */
class ScoreBookMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		IDBConnection $db,
		protected readonly ScoreBookTagLinkMapper $scoreBookTagLinkMapper,
		protected readonly TagMapper $tagMapper,
	) {
		parent::__construct($db, 'osm_score_books', ScoreBook::class);
	}

	/**
	 * Find a score book by its ID.
	 *
	 * @param int $id
	 * @return ScoreBook
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): ScoreBook {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$scoreBook = $this->findEntity($qb);
		$this->attachTags($scoreBook);
		return $scoreBook;
	}

	/**
	 * Get all score books.
	 *
	 * @return ScoreBook[]
	 * @throws Exception
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName());
		$scoreBooks = $this->findEntities($qb);
		$this->attachTagsToMultiple($scoreBooks);
		return $scoreBooks;
	}

	/**
	 * Attach tags to a single score book.
	 *
	 * @param ScoreBook $scoreBook
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function attachTags(ScoreBook $scoreBook): void {
		$ids = $this->scoreBookTagLinkMapper->findTagIdsForScoreBook($scoreBook->getId());
		$names = [];
		foreach ($ids as $id) {
			$tag = $this->tagMapper->find($id);
			$names[] = $tag->getName();
		}
		$scoreBook->setTags($names);
	}

	/**
	 * Find score books by multiple IDs.
	 *
	 * @param int[] $ids
	 * @return ScoreBook[]
	 * @throws Exception
	 */
	public function findMultiple(array $ids): array {
		$ids = array_map('intval', $ids);
		if (empty($ids)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$scoreBooks = $this->findEntities($qb);
		$this->attachTagsToMultiple($scoreBooks);
		return $scoreBooks;
	}

	/**
	 * Attach tags to multiple score books efficiently.
	 *
	 * This method fetches all tag data in batches to avoid N+1 queries.
	 *
	 * @param ScoreBook[] $scoreBooks
	 * @throws Exception
	 */
	private function attachTagsToMultiple(array $scoreBooks): void {
		if (empty($scoreBooks)) {
			return;
		}

		// Get tag IDs for all score books in one query
		$scoreBookIds = array_map(fn (ScoreBook $sb) => $sb->getId(), $scoreBooks);
		$scoreBookTagIdsMap = $this->scoreBookTagLinkMapper->findTagIdsForScoreBooks($scoreBookIds);

		// Get a merged list of all unique tag IDs
		$allTagIds = [];
		foreach ($scoreBookTagIdsMap as $tagIds) {
			$allTagIds = array_merge($allTagIds, $tagIds);
		}
		$allTagIds = array_values(array_unique($allTagIds));

		// Get the mapping of tag IDs to their names
		$tagIdNameMap = [];
		foreach ($this->tagMapper->findMultiple($allTagIds) as $tag) {
			$tagIdNameMap[$tag->getId()] = $tag->getName();
		}

		// Attach the tag names to each score book
		foreach ($scoreBooks as $scoreBook) {
			$tagNames = [];
			$tagIds = $scoreBookTagIdsMap[$scoreBook->getId()] ?? [];
			foreach ($tagIds as $tagId) {
				if (isset($tagIdNameMap[$tagId])) {
					$tagNames[] = $tagIdNameMap[$tagId];
				}
			}
			$scoreBook->setTags($tagNames);
		}
	}
}
