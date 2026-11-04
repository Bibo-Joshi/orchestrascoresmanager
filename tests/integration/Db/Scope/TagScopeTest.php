<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\TagScope;
use OCA\OrchestraScoresManager\Policy\TagPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class TagScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_tags';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new TagPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new TagScope($permissionService);
	}

	protected function seed(): array {
		$mapper = new TagMapper($this->db);
		$rows = [];
		foreach (['first', 'second'] as $name) {
			$tag = new Tag();
			$tag->setName($name . '-' . uniqid());
			$tag = $mapper->insert($tag);
			$rows[$tag->getId()] = $tag;
		}
		return $rows;
	}
}
