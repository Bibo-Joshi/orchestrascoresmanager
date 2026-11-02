<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Policy\FolderCollectionVersionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\FolderCollectionVersionScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class FolderCollectionVersionScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_fc_versions';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new FolderCollectionVersionPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new FolderCollectionVersionScope($permissionService);
	}

	protected function seed(): array {
		$fcMapper = new FolderCollectionMapper($this->db);
		$mapper = new FolderCollectionVersionMapper($this->db);
		$fc = new FolderCollection();
		$fc->setTitle('Collection');
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		$fc = $fcMapper->insert($fc);

		$rows = [];
		foreach ([['2024-01-01', '2024-06-01'], ['2024-06-01', null]] as [$from, $to]) {
			$version = new FolderCollectionVersion();
			$version->setFolderCollectionId($fc->getId());
			$version->setValidFrom(new DateTimeImmutable($from));
			$version->setValidTo($to === null ? null : new DateTimeImmutable($to));
			$version = $mapper->insert($version);
			$rows[$version->getId()] = $version;
		}
		return $rows;
	}
}
