<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Policy;

use OCA\OrchestraScoresManager\Policy\ClefPolicy;
use OCA\OrchestraScoresManager\Policy\ConductorsScorePolicy;
use OCA\OrchestraScoresManager\Policy\ConductorsScoreVariantPolicy;
use OCA\OrchestraScoresManager\Policy\NotationPresetPolicy;
use OCA\OrchestraScoresManager\Policy\PartPolicy;
use OCA\OrchestraScoresManager\Policy\PartVariantPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the notation and part related policies, which all follow ScorePolicy.
 */
final class PartAndNotationPoliciesTest extends TestCase {
	/**
	 * @return array<string, array{class-string<PolicyInterface>}>
	 */
	public static function policies(): array {
		return [
			'part' => [PartPolicy::class],
			'part variant' => [PartVariantPolicy::class],
			'conductors score' => [ConductorsScorePolicy::class],
			'conductors score variant' => [ConductorsScoreVariantPolicy::class],
			'clef' => [ClefPolicy::class],
			'notation preset' => [NotationPresetPolicy::class],
		];
	}

	/**
	 * @param class-string<PolicyInterface> $class
	 */
	#[DataProvider('policies')]
	public function testReadIsAlwaysAllowed(string $class): void {
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->expects($this->never())->method('canCurrentUserEdit');

		$this->assertTrue((new $class($permissionService))->allows(PolicyInterface::ACTION_READ));
	}

	/**
	 * @param class-string<PolicyInterface> $class
	 */
	#[DataProvider('policies')]
	public function testWriteActionsFollowEditPermission(string $class): void {
		foreach ([true, false] as $canEdit) {
			$permissionService = $this->createMock(PermissionService::class);
			$permissionService->method('canCurrentUserEdit')->willReturn($canEdit);
			$policy = new $class($permissionService);
			foreach ([PolicyInterface::ACTION_CREATE, PolicyInterface::ACTION_UPDATE, PolicyInterface::ACTION_DELETE, PolicyInterface::ACTION_SHARE] as $action) {
				$this->assertSame($canEdit, $policy->allows($action));
			}
		}
	}

	/**
	 * @param class-string<PolicyInterface> $class
	 */
	#[DataProvider('policies')]
	public function testUnknownActionIsDenied(string $class): void {
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canCurrentUserEdit')->willReturn(true);

		$this->assertFalse((new $class($permissionService))->allows('unknown'));
	}
}
