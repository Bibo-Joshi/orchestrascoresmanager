<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\SetlistScope;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class SetlistScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_setlists';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new SetlistPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new SetlistScope($permissionService);
	}

	protected function seed(): array {
		$mapper = new SetlistMapper($this->db, new SetlistScope($this->createMock(PermissionService::class)));
		$rows = [];
		foreach ([true, false, true, false] as $index => $published) {
			$setlist = new Setlist();
			$setlist->setTitle('Setlist ' . $index);
			$setlist->setIsPublished($published);
			$setlist = $mapper->insert($setlist);
			$rows[$setlist->getId()] = $setlist;
		}
		return $rows;
	}
}
