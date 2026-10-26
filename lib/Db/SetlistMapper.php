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
 * @extends QBMapper<Setlist>
 */
class SetlistMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_setlists', Setlist::class);
	}

	/**
	 * @param bool|null $isDraft Filter by draft status
	 * @param bool|null $isPublished Filter by published status
	 * @return Setlist[]
	 * @throws Exception
	 */
	public function findAll(?bool $isDraft = null, ?bool $isPublished = null): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());

		$this->applyStatusFilters($qb, $isDraft, $isPublished);

		$qb->orderBy('start_date_time', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Find all future setlists (start_date_time + duration >= now)
	 * @param bool|null $isDraft Filter by draft status
	 * @param bool|null $isPublished Filter by published status
	 * @return Setlist[]
	 * @throws Exception
	 */
	public function findFuture(?bool $isDraft = null, ?bool $isPublished = null): array {
		$now = new \DateTimeImmutable();
		$setlists = $this->findAll($isDraft, $isPublished);
		return array_values(array_filter($setlists, fn (Setlist $s) => $this->getEndDateTime($s) >= $now));
	}

	/**
	 * Find all past setlists (start_date_time + duration < now), ordered by start date descending.
	 * @param bool|null $isDraft Filter by draft status
	 * @param bool|null $isPublished Filter by published status
	 * @return Setlist[]
	 * @throws Exception
	 */
	public function findPast(?bool $isDraft = null, ?bool $isPublished = null): array {
		$now = new \DateTimeImmutable();
		$setlists = $this->findAll($isDraft, $isPublished);
		$past = array_values(array_filter($setlists, fn (Setlist $s) => $this->getEndDateTime($s) < $now));
		return array_reverse($past);
	}

	/**
	 * Calculate the end date/time of a setlist as start_date_time + duration seconds.
	 * Falls back to start_date_time when duration is null, and to the Unix epoch when start is null.
	 *
	 * @param Setlist $setlist
	 * @return \DateTimeImmutable
	 */
	private function getEndDateTime(Setlist $setlist): \DateTimeImmutable {
		$start = $setlist->getStartDateTime() ?? new \DateTimeImmutable('@0');
		$duration = $setlist->getDuration() ?? 0;
		$modified = $start->modify("+{$duration} seconds");
		return $modified === false ? $start : $modified;
	}

	/**
	 * Find setlist by id
	 * @param int $id
	 * @return Setlist
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): Setlist {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find a page of setlists that contain the given score, sorted by the given column.
	 * Sorting by 'creation' uses the order of creation (id), as setlists have no creation timestamp.
	 * Setlists without a start date are considered newer than any setlist with a start date when sorting by 'date'.
	 *
	 * @param int $scoreId
	 * @param bool $publishedOnly Restrict to published setlists
	 * @param string $sortBy One of 'creation', 'date', 'name'
	 * @param string $sortOrder 'ASC' or 'DESC'
	 * @param int $limit Maximum number of setlists to return
	 * @param int $offset Number of setlists to skip
	 * @return Setlist[]
	 * @throws Exception
	 */
	public function findByScoreId(int $scoreId, bool $publishedOnly, string $sortBy, string $sortOrder, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		$this->applyScoreFilter($qb, $scoreId, $publishedOnly);

		$direction = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
		$column = match ($sortBy) {
			'name' => 'title',
			'date' => 'start_date_time',
			default => 'id',
		};
		if ($column === 'start_date_time') {
			// portable NULL ordering: unscheduled setlists count as newer than all scheduled ones
			$qb->orderBy($qb->createFunction('CASE WHEN start_date_time IS NULL THEN 1 ELSE 0 END'), $direction);
			$qb->addOrderBy($column, $direction);
		} else {
			$qb->orderBy($column, $direction);
		}
		if ($column !== 'id') {
			$qb->addOrderBy('id', $direction);
		}
		$qb->setMaxResults($limit)->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	/**
	 * Count the setlists that contain the given score.
	 *
	 * @param int $scoreId
	 * @param bool $publishedOnly Restrict to published setlists
	 * @return int
	 * @throws Exception
	 */
	public function countByScoreId(int $scoreId, bool $publishedOnly): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($this->getTableName());
		$this->applyScoreFilter($qb, $scoreId, $publishedOnly);

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/**
	 * Restrict the query to setlists containing the score (and optionally only published ones).
	 *
	 * @param IQueryBuilder $qb
	 * @param int $scoreId
	 * @param bool $publishedOnly
	 * @return void
	 */
	private function applyScoreFilter(IQueryBuilder $qb, int $scoreId, bool $publishedOnly): void {
		$sub = $this->db->getQueryBuilder();
		$sub->select('setlist_id')
			->from('osm_setlist_entries')
			->where($sub->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));

		$qb->where($qb->expr()->in('id', $qb->createFunction($sub->getSQL())));
		if ($publishedOnly) {
			$qb->andWhere($qb->expr()->eq('is_published', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}
	}

	/**
	 * Apply isDraft and isPublished filters to query builder if provided
	 *
	 * @param IQueryBuilder $qb
	 * @param bool|null $isDraft
	 * @param bool|null $isPublished
	 * @return void
	 */
	private function applyStatusFilters(IQueryBuilder $qb, ?bool $isDraft, ?bool $isPublished): void {
		if ($isDraft !== null) {
			$qb->andWhere($qb->expr()->eq('is_draft', $qb->createNamedParameter($isDraft, IQueryBuilder::PARAM_BOOL)));
		}
		if ($isPublished !== null) {
			$qb->andWhere($qb->expr()->eq('is_published', $qb->createNamedParameter($isPublished, IQueryBuilder::PARAM_BOOL)));
		}
	}
}
