<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Policy;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;
use PHPUnit\Framework\TestCase;

final class SetlistTemplatePolicyTest extends TestCase {
	public function testReadersCanReadButNotEdit(): void {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canCurrentUserEdit')->willReturn(false);
		$policy = new SetlistTemplatePolicy($permissions);

		$this->assertTrue($policy->allows(PolicyInterface::ACTION_READ));
		foreach ([PolicyInterface::ACTION_CREATE, PolicyInterface::ACTION_UPDATE, PolicyInterface::ACTION_DELETE] as $action) {
			$this->assertFalse($policy->allows($action));
		}
	}

	public function testEditorsCanManageTemplates(): void {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canCurrentUserEdit')->willReturn(true);
		$policy = new SetlistTemplatePolicy($permissions);

		foreach ([PolicyInterface::ACTION_READ, PolicyInterface::ACTION_CREATE, PolicyInterface::ACTION_UPDATE, PolicyInterface::ACTION_DELETE] as $action) {
			$this->assertTrue($policy->allows($action));
		}
		$this->assertFalse($policy->allows(PolicyInterface::ACTION_SHARE));
	}
}
