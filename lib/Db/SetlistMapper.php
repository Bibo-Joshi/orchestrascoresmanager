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
