<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Policy;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistPolicy.
 *
 * Published setlists are readable by all users.
 * Drafts and other operations require edit permissions.
 */
final class SetlistPolicyTest extends TestCase {
	private PermissionService $permissionService;
	private SetlistPolicy $policy;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionService = $this->createMock(PermissionService::class);
		$this->policy = new SetlistPolicy($this->permissionService);
	}

	/**
	 * Test that actions behave as expected based on user permissions and setlist state.
	 *
	 * @param string $action The action to test
	 * @param bool|null $isPublished Whether the setlist is published (null for no resource)
	 * @param bool|null $canUserEdit Whether the user can edit (null means permission service should not be called)
	 * @param bool $expectedResult The expected result of the allows() call
	 */
	#[DataProvider('allowsProvider')]
	public function testAllows(string $action, ?bool $isPublished, ?bool $canUserEdit, bool $expectedResult): void {
		if ($canUserEdit === null) {
			$this->permissionService->expects($this->never())
				->method('canCurrentUserEdit');
		} else {
			$this->permissionService->expects($this->once())
				->method('canCurrentUserEdit')
				->willReturn($canUserEdit);
		}

		$resource = null;
		if ($isPublished !== null) {
			$setlist = new Setlist();
			$setlist->setIsPublished($isPublished);
			$resource = $setlist;
		}

		$result = $this->policy->allows($action, $resource);

		$this->assertSame($expectedResult, $result);
	}

	public static function allowsProvider(): array {
		return [
			// CREATE - requires edit permission
			'CREATE action allowed when user can edit' => [PolicyInterface::ACTION_CREATE, null, true, true],
			'CREATE action denied when user cannot edit' => [PolicyInterface::ACTION_CREATE, null, false, false],

			// READ - published setlists are public, drafts require edit
			'READ published setlist without edit' => [PolicyInterface::ACTION_READ, true, null, true],
			'READ published setlist with edit' => [PolicyInterface::ACTION_READ, true, null, true],
			'READ draft setlist with edit' => [PolicyInterface::ACTION_READ, false, true, true],
			'READ draft setlist without edit' => [PolicyInterface::ACTION_READ, false, false, false],

			// UPDATE - requires edit permission
			'UPDATE action allowed when user can edit' => [PolicyInterface::ACTION_UPDATE, false, true, true],
			'UPDATE action denied when user cannot edit' => [PolicyInterface::ACTION_UPDATE, false, false, false],

			// DELETE - requires edit permission
			'DELETE action allowed when user can edit' => [PolicyInterface::ACTION_DELETE, false, true, true],
			'DELETE action denied when user cannot edit' => [PolicyInterface::ACTION_DELETE, false, false, false],

			// SHARE - requires edit permission
			'SHARE action allowed when user can edit' => [PolicyInterface::ACTION_SHARE, null, true, true],
			'SHARE action denied when user cannot edit' => [PolicyInterface::ACTION_SHARE, null, false, false],

			// Unknown action - always denied
			'Unknown action is always denied' => ['UNKNOWN_ACTION', null, null, false],
		];
	}
}
