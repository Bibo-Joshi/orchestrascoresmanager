<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Policy\ClefPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ClefScope;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ClefScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_clefs';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ClefPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ClefScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		foreach ([['G', 2], ['percussion', null]] as [$sign, $line]) {
			$clef = $this->insertClef($sign, $line);
			$rows[$clef->getId()] = $clef;
		}
		return $rows;
	}
}
