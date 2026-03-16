<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Service\ConfigService;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigService.
 */
final class ConfigServiceTest extends TestCase {
	private IAppConfig $appConfig;
	private ConfigService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new ConfigService($this->appConfig);
	}

	public function testGetAllowedEditGroups(): void {
		$expectedGroups = ['admin', 'editors', 'moderators'];

		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('allowed_edit_groups')
			->willReturn($expectedGroups);

		$result = $this->service->getAllowedEditGroups();

		$this->assertSame($expectedGroups, $result);
	}

	public function testGetAllowedEditGroupsReturnsEmptyArray(): void {
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('allowed_edit_groups')
			->willReturn([]);

		$result = $this->service->getAllowedEditGroups();

		$this->assertSame([], $result);
	}

	public function testSetAllowedEditGroups(): void {
		$groups = ['admin', 'editors'];

		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with('allowed_edit_groups', $groups);

		$this->service->setAllowedEditGroups($groups);
	}

	public function testSetAllowedEditGroupsRemovesEmptyValues(): void {
		$groups = ['admin', '', 'editors', null, 'moderators'];
		$expectedGroups = ['admin', 'editors', 'moderators'];

		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with('allowed_edit_groups', $expectedGroups);

		$this->service->setAllowedEditGroups($groups);
	}

	public function testSetAllowedEditGroupsRemovesDuplicates(): void {
		$groups = ['admin', 'editors', 'admin', 'moderators', 'editors'];
		$expectedGroups = ['admin', 'editors', 'moderators'];

		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with('allowed_edit_groups', $expectedGroups);

		$this->service->setAllowedEditGroups($groups);
	}

	#[TestWith(['admin', ['admin', 'editors'], true])]
	#[TestWith(['moderators', ['admin', 'editors'], false])]
	#[TestWith(['admin', [], false])]
	#[TestWith(['editors', ['editors'], true])]
	public function testIsGroupAllowedToEdit(string $groupId, array $allowedGroups, bool $expected): void {
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('allowed_edit_groups')
			->willReturn($allowedGroups);

		$result = $this->service->isGroupAllowedToEdit($groupId);

		$this->assertSame($expected, $result);
	}
}
