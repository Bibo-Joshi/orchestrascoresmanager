<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\SetlistTemplateScope;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class SetlistTemplateScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_setlist_templates';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new SetlistTemplatePolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new SetlistTemplateScope($permissionService);
	}

	protected function seed(): array {
		$mapper = new SetlistTemplateMapper($this->db);
		$rows = [];
		$now = new DateTimeImmutable();

		foreach (['Stage', 'Concert'] as $name) {
			$template = SetlistTemplate::fromParams([
				'name' => $name,
				'configJson' => '{"fontSizes":[],"margin":8,"cellPadding":1.5,"rules":[]}',
				'createdBy' => 'editor',
				'createdAt' => $now,
				'updatedAt' => $now,
			]);

			$template = $mapper->insert($template);
			$rows[$template->getId()] = $template;
		}

		return $rows;
	}
}
