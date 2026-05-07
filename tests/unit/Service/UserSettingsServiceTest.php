<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Service\UserSettingsService;
use OCP\Config\IUserConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserSettingsService.
 */
final class UserSettingsServiceTest extends TestCase {
	private IUserConfig $userConfig;
	private UserSettingsService $service;
	private const APP_NAME = 'orchestrascoresmanager';
	private const USER_ID = 'testuser';

	protected function setUp(): void {
		parent::setUp();

		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->service = new UserSettingsService($this->userConfig, self::APP_NAME);
	}

	public function testGetSetlistSettingsReturnsNullsWhenNotSet(): void {
		$this->userConfig->expects($this->exactly(2))
			->method('getValueString')
			->willReturn('');

		$result = $this->service->getSetlistSettings(self::USER_ID);

		$this->assertNull($result['defaultModerationTime']);
		$this->assertNull($result['defaultFolderCollectionId']);
	}

	public function testGetSetlistSettingsReturnsModerationTime(): void {
		$this->userConfig->expects($this->exactly(2))
			->method('getValueInt')
			->willReturnCallback(function (string $userId, string $app, string $key): ?int {
				return $key === 'setlists_default_moderation_time' ? 60 : null;
			});

		$result = $this->service->getSetlistSettings(self::USER_ID);

		$this->assertSame(60, $result['defaultModerationTime']);
		$this->assertNull($result['defaultFolderCollectionId']);
	}

	public function testGetSetlistSettingsReturnsFolderCollectionId(): void {
		$this->userConfig->expects($this->exactly(2))
			->method('getValueInt')
			->willReturnCallback(function (string $userId, string $app, string $key): ?int {
				return $key === 'setlists_default_folder_collection_id' ? 42 : null;
			});

		$result = $this->service->getSetlistSettings(self::USER_ID);

		$this->assertNull($result['defaultModerationTime']);
		$this->assertSame(42, $result['defaultFolderCollectionId']);
	}

	public function testUpdateSetlistSettingsSetsValues(): void {
		$calls = [];

		$this->userConfig->expects($this->exactly(2))
			->method('setValueString')
			->willReturnCallback(function (string $userId, string $app, string $key, string $value, bool $lazy) use (&$calls): bool {
				$calls[] = [$userId, $app, $key, $value, $lazy];
				return true;
			});

		$this->userConfig->expects($this->never())
			->method('deleteUserValue');

		$this->service->updateSetlistSettings(self::USER_ID, 30, 7);

		$this->assertSame([self::USER_ID, self::APP_NAME, 'setlists_default_moderation_time', 30, true], $calls[0]);
		$this->assertSame([self::USER_ID, self::APP_NAME, 'setlists_default_folder_collection_id', 7, true], $calls[1]);
	}

	public function testUpdateSetlistSettingsDeletesWhenNull(): void {
		$this->userConfig->expects($this->never())
			->method('setValueString');

		$deleteCalls = [];
		$this->userConfig->expects($this->exactly(2))
			->method('deleteUserConfig')
			->willReturnCallback(function (string $userId, string $app, string $key) use (&$deleteCalls): void {
				$deleteCalls[] = [$userId, $app, $key];
			});

		$this->service->updateSetlistSettings(self::USER_ID, null, null);

		$this->assertSame([self::USER_ID, self::APP_NAME, 'setlists_default_moderation_time'], $deleteCalls[0]);
		$this->assertSame([self::USER_ID, self::APP_NAME, 'setlists_default_folder_collection_id'], $deleteCalls[1]);
	}
}
