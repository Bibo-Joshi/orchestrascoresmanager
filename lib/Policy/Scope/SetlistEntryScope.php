<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Query scope for setlist entries. Entries inherit the rules of their setlist.
 */
class SetlistEntryScope implements EntityScopeInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly SetlistScope $setlistScope,
		private readonly IDBConnection $db,
	) {
	}

	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void {
		$condition = $this->setlistScope->buildCondition($action, $qb, 'parent_setlist');
		if ($condition === null) {
			return;
		}

		$sub = $this->db->getQueryBuilder();
		$sub->select('parent_setlist.id')
			->from('osm_setlists', 'parent_setlist')
			->where($condition);

		$column = ($alias === '' ? '' : $alias . '.') . 'setlist_id';
		$qb->andWhere($qb->expr()->in($column, $qb->createFunction($sub->getSQL())));
	}
}
