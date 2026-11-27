<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Policy\ConductorsScorePolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ConductorsScoreScope;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ConductorsScoreScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_conductors_scores';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ConductorsScorePolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ConductorsScoreScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		for ($i = 0; $i < 2; $i++) {
			$cs = $this->insertConductorsScore();
			$rows[$cs->getId()] = $cs;
		}
		return $rows;
	}
}
