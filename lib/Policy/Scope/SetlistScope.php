<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Query scope mirroring SetlistPolicy.
 */
class SetlistScope implements EntityScopeInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void {
		$condition = $this->buildCondition($action, $qb, $alias);
		if ($condition !== null) {
			$qb->andWhere($condition);
		}
	}

	/**
	 * Build the condition for the action, binding parameters on the given query builder.
	 * Allows reuse in subqueries of other queries, which must own the bound parameters.
	 *
	 * @param string $action One of the PolicyInterface::ACTION_* constants
	 * @param IQueryBuilder $qb Query builder used to bind parameters
	 * @param string $alias Table alias, empty if the table is not aliased
	 * @return string|null The condition, or null if the action is allowed without restriction
	 */
	public function buildCondition(string $action, IQueryBuilder $qb, string $alias = ''): ?string {
		return match ($action) {
			PolicyInterface::ACTION_CREATE,
			PolicyInterface::ACTION_UPDATE,
			PolicyInterface::ACTION_DELETE,
			PolicyInterface::ACTION_SHARE => $this->permissionService->canCurrentUserEdit() ? null : self::DENY_CONDITION,
			PolicyInterface::ACTION_READ => $this->permissionService->canCurrentUserEdit()
				? null
				: (string)$qb->expr()->eq(
					($alias === '' ? '' : $alias . '.') . 'is_published',
					$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				),
			default => self::DENY_CONDITION,
		};
	}
}
