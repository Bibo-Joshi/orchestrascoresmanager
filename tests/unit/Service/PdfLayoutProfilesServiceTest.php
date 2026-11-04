<?php

declare(strict_types=1);

/**
 * Unit tests for per-user PDF layout profile persistence.
 *
 * Add to: tests/unit/Service/PdfLayoutProfilesServiceTest.php
 * The tests use a mocked Nextcloud IUserConfig and never modify real settings.
 */

use OCA\OrchestraScoresManager\Service\UserSettingsService;
use OCP\Config\IUserConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfLayoutProfilesServiceTest extends TestCase {
	private const APP_ID = 'orchestrascoresmanager';
	private const USER_ID = 'alice';
	private const CONFIG_KEY = 'pdf_layout_profiles_v1';

	/**
	 * @return array<string, array{string, ?array}>
	 */
	public static function storedConfigurations(): array {
		$profile = self::profile('stage');
		return [
			'not yet configured' => ['__unconfigured__', null],
			'intentionally empty list' => ['[]', []],
			'valid profiles' => [json_encode([$profile], JSON_THROW_ON_ERROR), [$profile]],
			'malformed JSON' => ['{not valid JSON', []],
			'object instead of list' => ['{"id":"wrong"}', []],
		];
	}

	#[DataProvider('storedConfigurations')]
	public function testGetPdfLayoutsHandlesStoredConfigurations(string $stored, ?array $expected): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::once())
			->method('getValueString')
			->with(self::USER_ID, self::APP_ID, self::CONFIG_KEY, '__unconfigured__', true)
			->willReturn($stored);

		self::assertSame($expected, $this->service($config)->getPdfLayouts(self::USER_ID));
	}

	public function testPutPdfLayoutsSavesJsonAndReturnsProfiles(): void {
		$profiles = [
			self::profile('stage', 'Bühnenlayout', [
				['id' => 'duet', 'label' => 'Duett', 'expression' => 'Male & Female', 'color' => '#FFE0B2'],
			]),
			self::profile('concert', 'Concert program'),
		];

		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::once())
			->method('setValueString')
			->with(
				self::USER_ID,
				self::APP_ID,
				self::CONFIG_KEY,
				self::callback(static function (string $json) use ($profiles): bool {
					return json_decode($json, true, 512, JSON_THROW_ON_ERROR) === $profiles
						&& str_contains($json, 'Bühnenlayout');
				}),
				true,
			);

		self::assertSame($profiles, $this->service($config)->putPdfLayouts(self::USER_ID, $profiles));
	}

	public function testEmptyProfileListIsPersistedRatherThanReplacedWithDefaults(): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::once())
			->method('setValueString')
			->with(self::USER_ID, self::APP_ID, self::CONFIG_KEY, '[]', true);

		self::assertSame([], $this->service($config)->putPdfLayouts(self::USER_ID, []));
	}

	public function testPutPdfLayoutsDoesNotModifyOtherUsersSettings(): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::exactly(2))
			->method('setValueString')
			->willReturnCallback(static function (string $user, string $app, string $key, string $json, bool $flag): bool {
				self::assertContains($user, ['alice', 'bob']);
				self::assertSame(self::APP_ID, $app);
				self::assertSame(self::CONFIG_KEY, $key);
				self::assertSame('[]', $json);
				self::assertTrue($flag);
                                return true;
			});

		$service = $this->service($config);
		$service->putPdfLayouts('alice', []);
		$service->putPdfLayouts('bob', []);
	}

	/**
	 * @return array<string, array{array}>
	 */
	public static function invalidConfigurations(): array {
		$tooManyProfiles = [];
		for ($i = 0; $i < 13; $i++) {
			$tooManyProfiles[] = self::profile('profile-' . $i);
		}

		$tooManyRules = self::profile('stage');
		$tooManyRules['rules'] = array_fill(0, 31, ['id' => 'x']);

		$oversized = [];
		for ($i = 0; $i < 12; $i++) {
			$p = self::profile('profile-' . $i);
			$p['rules'] = array_fill(0, 30, ['expression' => str_repeat('x', 200)]);
			$oversized[] = $p;
		}

		return [
			'associative top-level structure' => [['profile' => self::profile('stage')]],
			'too many profiles' => [$tooManyProfiles],
			'reserved standard ID' => [[self::profile('standard')]],
			'duplicate IDs' => [[self::profile('stage'), self::profile('stage')]],
			'empty ID' => [[self::profile('')]],
			'ID longer than 64 bytes' => [[self::profile(str_repeat('a', 65))]],
			'non-string ID' => [[[ 'id' => 123, 'name' => 'Stage', 'rules' => [] ]]],
			'missing name' => [[[ 'id' => 'stage', 'rules' => [] ]]],
			'blank name' => [[self::profile('stage', '  ')]],
			'name longer than 160 bytes' => [[self::profile('stage', str_repeat('a', 161))]],
			'missing rules' => [[[ 'id' => 'stage', 'name' => 'Stage' ]]],
			'associative rules' => [[[ 'id' => 'stage', 'name' => 'Stage', 'rules' => ['one' => []] ]]],
			'more than 30 rules' => [[$tooManyRules]],
			'more than 45 kB of JSON' => [$oversized],
		];
	}

	#[DataProvider('invalidConfigurations')]
	public function testPutPdfLayoutsRejectsInvalidInputWithoutWriting(array $profiles): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::never())->method('setValueString');

		$this->expectException(\InvalidArgumentException::class);
		$this->service($config)->putPdfLayouts(self::USER_ID, $profiles);
	}

	/** @return array{id: string, name: string, rules: array} */
	private static function profile(string $id, string $name = 'Stage', array $rules = []): array {
		return ['id' => $id, 'name' => $name, 'rules' => $rules];
	}

	private function service(IUserConfig $config): UserSettingsService {
		return new UserSettingsService($config, self::APP_ID);
	}
}
