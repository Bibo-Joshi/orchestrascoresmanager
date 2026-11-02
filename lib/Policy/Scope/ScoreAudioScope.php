<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy\Scope;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Query scope mirroring ScoreAudioPolicy.
 */
class ScoreAudioScope implements EntityScopeInterface {

	public function apply(string $action, IQueryBuilder $qb, string $alias = ''): void {
		$allowed = match ($action) {
			PolicyInterface::ACTION_CREATE,
			PolicyInterface::ACTION_READ,
			PolicyInterface::ACTION_UPDATE,
			PolicyInterface::ACTION_DELETE,
			PolicyInterface::ACTION_SHARE => true,
			default => false,
		};
		if (!$allowed) {
			$qb->andWhere(self::DENY_CONDITION);
		}
	}
}
