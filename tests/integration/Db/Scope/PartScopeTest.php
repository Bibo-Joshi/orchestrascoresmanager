<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Policy\PartPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\PartScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class PartScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_parts';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new PartPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new PartScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		for ($i = 0; $i < 2; $i++) {
			$part = $this->insertPart();
			$rows[$part->getId()] = $part;
		}
		return $rows;
	}
}
