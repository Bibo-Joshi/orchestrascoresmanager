<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * A Scope constrains a query to the rows the corresponding Policy would allow for an action.
 *
 * Policies answer for a single entity, Scopes answer for a whole query. Both must stay in sync.
 */
interface EntityScopeInterface {
	/** SQL condition that never matches */
	public const DENY_CONDITION = '1 = 0';

	/**
	 * Constrain the query to the rows for which the policy allows the action.
	 * Denied and unknown actions match no rows, allowed actions without restriction leave the query untouched.
	 *
	 * @param string $action One of the PolicyInterface::ACTION_* constants
	 * @param IQueryBuilder $qb The query to constrain
	 * @param string $alias Table alias used in the query, empty if the table is not aliased
	 * @return void
	 */
	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void;
}
