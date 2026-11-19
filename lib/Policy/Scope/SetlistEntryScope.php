<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Query scope for setlist entries. Entries inherit the rules of their setlist.
 * @psalm-suppress UnusedClass - Not wired into a mapper until a rule needs it
 */
class SetlistEntryScope implements EntityScopeInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly SetlistScope $setlistScope,
		private readonly IDBConnection $db,
	) {
	}

	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void {
		// 'parent_setlist' is not a table, but the SQL alias given to osm_setlists in the subquery below.
		// It qualifies the column names of the setlist condition, so they refer to the setlist and not to the entry.
		$condition = $this->setlistScope->buildCondition($action, $qb, 'parent_setlist');
		if ($condition === null) {
			return;
		}

		// Subquery: IDs of all setlists the setlist scope allows for the action
		$sub = $this->db->getQueryBuilder();
		$sub->select('parent_setlist.id')
			->from('osm_setlists', 'parent_setlist')
			->where($condition);

		$column = ($alias === '' ? '' : $alias . '.') . 'setlist_id';
		$qb->andWhere($qb->expr()->in($column, $qb->createFunction($sub->getSQL())));
	}
}
