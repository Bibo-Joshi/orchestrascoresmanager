<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Policy\FolderCollectionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\FolderCollectionScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class FolderCollectionScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_folder_collections';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new FolderCollectionPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new FolderCollectionScope($permissionService);
	}

	protected function seed(): array {
		$mapper = new FolderCollectionMapper($this->db);
		$rows = [];
		foreach (['first', 'second'] as $title) {
			$fc = new FolderCollection();
			$fc->setTitle($title);
			$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
			$fc = $mapper->insert($fc);
			$rows[$fc->getId()] = $fc;
		}
		return $rows;
	}
}
