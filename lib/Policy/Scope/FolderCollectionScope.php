<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Query scope mirroring FolderCollectionPolicy.
 * @psalm-suppress UnusedClass - Not wired into a mapper until a rule needs it
 */
class FolderCollectionScope implements EntityScopeInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void {
		$allowed = match ($action) {
			PolicyInterface::ACTION_CREATE,
			PolicyInterface::ACTION_UPDATE,
			PolicyInterface::ACTION_DELETE,
			PolicyInterface::ACTION_SHARE => $this->permissionService->canCurrentUserEdit(),
			PolicyInterface::ACTION_READ => true,
			default => false,
		};
		if (!$allowed) {
			$qb->andWhere(self::DENY_CONDITION);
		}
	}
}
