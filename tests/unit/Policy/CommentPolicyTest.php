<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Policy;

use OCA\OrchestraScoresManager\Policy\CommentPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CommentPolicy.
 *
 * Note: CommentPolicy differs from other policies in that READ access
 * also requires edit permission. This is intentional - comments should
 * only be visible to users who can edit the database.
 */
final class CommentPolicyTest extends TestCase {
	private PermissionService $permissionService;
	private CommentPolicy $policy;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionService = $this->createMock(PermissionService::class);
		$this->policy = new CommentPolicy($this->permissionService);
	}

	/**
	 * Test that actions behave as expected based on user permissions.
	 *
	 * Note: Unlike other policies, READ also requires edit permission for comments.
	 *
	 * @param string $action The action to test
	 * @param bool|null $canUserEdit Whether the user can edit (null means permission service should not be called)
	 * @param bool $expectedResult The expected result of the allows() call
	 */
	#[DataProvider('allowsProvider')]
	public function testAllows(string $action, ?bool $canUserEdit, bool $expectedResult): void {
		if ($canUserEdit === null) {
			$this->permissionService->expects($this->never())
				->method('canCurrentUserEdit');
		} else {
			$this->permissionService->expects($this->once())
				->method('canCurrentUserEdit')
				->willReturn($canUserEdit);
		}

		$result = $this->policy->allows($action);

		$this->assertSame($expectedResult, $result);
	}

	public static function allowsProvider(): array {
		return [
			'CREATE action allowed when user can edit' => [PolicyInterface::ACTION_CREATE, true, true],
			'CREATE action denied when user cannot edit' => [PolicyInterface::ACTION_CREATE, false, false],
			'READ action allowed when user can edit' => [PolicyInterface::ACTION_READ, true, true],
			'READ action denied when user cannot edit' => [PolicyInterface::ACTION_READ, false, false],
			'DELETE action allowed when user can edit' => [PolicyInterface::ACTION_DELETE, true, true],
			'DELETE action denied when user cannot edit' => [PolicyInterface::ACTION_DELETE, false, false],
			'UPDATE action is always denied' => [PolicyInterface::ACTION_UPDATE, null, false],
			'SHARE action is always denied' => [PolicyInterface::ACTION_SHARE, null, false],
			'Unknown action is always denied' => ['UNKNOWN_ACTION', null, false],
		];
	}
}
