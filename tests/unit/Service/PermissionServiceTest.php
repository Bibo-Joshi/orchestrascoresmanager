<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Service\ConfigService;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PermissionService.
 */
final class PermissionServiceTest extends TestCase {
	private ConfigService $configService;
	private IGroupManager $groupManager;
	private IUserSession $userSession;
	private PermissionService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->service = new PermissionService(
			$this->configService,
			$this->groupManager,
			$this->userSession
		);
	}

	public function testCanCurrentUserEditReturnsFalseWhenNoUserLoggedIn(): void {
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$result = $this->service->canCurrentUserEdit();

		$this->assertFalse($result);
	}

	public function testCanCurrentUserEditReturnsFalseWhenNoAllowedGroups(): void {
		$user = $this->createMock(IUser::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn([]);

		$result = $this->service->canCurrentUserEdit();

		$this->assertFalse($result);
	}

	public function testCanCurrentUserEditReturnsTrueWhenUserIsInAllowedGroup(): void {
		$user = $this->createMock(IUser::class);
		$group1 = $this->createMock(IGroup::class);
		$group2 = $this->createMock(IGroup::class);

		$group1->expects($this->once())
			->method('getGID')
			->willReturn('editors');

		$group2->expects($this->never())
			->method('getGID');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($user)
			->willReturn([$group1, $group2]);

		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn(['admin', 'editors']);

		$result = $this->service->canCurrentUserEdit();

		$this->assertTrue($result);
	}

	public function testCanCurrentUserEditReturnsFalseWhenUserNotInAllowedGroup(): void {
		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);

		$group->expects($this->once())
			->method('getGID')
			->willReturn('viewers');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($user)
			->willReturn([$group]);

		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn(['admin', 'editors']);

		$result = $this->service->canCurrentUserEdit();

		$this->assertFalse($result);
	}

	public function testCanCurrentUserEditChecksAllUserGroups(): void {
		$user = $this->createMock(IUser::class);
		$group1 = $this->createMock(IGroup::class);
		$group2 = $this->createMock(IGroup::class);
		$group3 = $this->createMock(IGroup::class);

		$group1->expects($this->once())
			->method('getGID')
			->willReturn('viewers');

		$group2->expects($this->once())
			->method('getGID')
			->willReturn('guests');

		$group3->expects($this->once())
			->method('getGID')
			->willReturn('admin');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($user)
			->willReturn([$group1, $group2, $group3]);

		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn(['admin']);

		$result = $this->service->canCurrentUserEdit();

		$this->assertTrue($result);
	}
}
