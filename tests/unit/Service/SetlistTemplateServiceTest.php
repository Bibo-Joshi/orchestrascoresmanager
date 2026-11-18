<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\SetlistTemplateService;
use OCA\OrchestraScoresManager\Service\SetlistTemplateValidator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateServiceTest extends TestCase {
	private SetlistTemplateMapper $mapper;
	private AuthorizationService $authorization;
	private SetlistTemplatePolicy $policy;
	private IUserSession $session;
	private SetlistTemplateService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(SetlistTemplateMapper::class);
		$this->authorization = $this->createMock(AuthorizationService::class);
		$this->policy = $this->createMock(SetlistTemplatePolicy::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->service = new SetlistTemplateService(
			$this->mapper,
			new SetlistTemplateValidator(),
			$this->authorization,
			$this->policy,
			$this->session
		);
	}

	private static function validInput(): array {
		return [
			'name' => 'Stage layout',
			'description' => null,
			'fontSizes' => ['title' => 16, 'scoreRow' => 8],
			'margin' => 8,
			'cellPadding' => 1.5,
			'rules' => [],
		];
	}

	public function testGetTemplatesAuthorizesRead(): void {
		$expected = [['id' => 'stage', ...self::validInput()]];
		$this->authorization->expects($this->once())
			->method('authorizePolicy')->with($this->policy, PolicyInterface::ACTION_READ);
		$this->mapper->expects($this->once())->method('findAll')->willReturn($expected);
		$this->assertSame($expected, $this->service->getTemplates());
	}

	public function testCreateValidatesAndPersistsWithCreator(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('editor');
		$this->session->method('getUser')->willReturn($user);
		$this->authorization->expects($this->once())
			->method('authorizePolicy')->with($this->policy, PolicyInterface::ACTION_CREATE);
		$this->mapper->expects($this->once())
			->method('create')
			->with(
				$this->callback(fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1),
				'Stage layout',
				null,
				$this->callback(fn (array $config): bool => $config['margin'] === 8.0 && $config['rules'] === []),
				'editor'
			)
			->willReturn(['id' => 'generated', ...self::validInput()]);
		$this->assertSame('generated', $this->service->createTemplate(self::validInput())['id']);
	}

	public function testPatchMergesAndValidates(): void {
		$this->authorization->expects($this->once())
			->method('authorizePolicy')->with($this->policy, PolicyInterface::ACTION_UPDATE);
		$this->mapper->method('find')->with('stage')->willReturn([
			'id' => 'stage', ...self::validInput(),
			'createdBy' => 'alice', 'createdAt' => 'now', 'updatedAt' => 'now',
		]);
		$this->mapper->expects($this->once())->method('update')
			->with('stage', 'Stage layout', null, $this->callback(fn (array $config): bool => $config['margin'] === 12.0))
			->willReturn(['id' => 'stage', 'margin' => 12]);
		$this->assertSame(12, $this->service->patchTemplate('stage', ['margin' => 12])['margin']);
	}

	public function testPatchOneFontSizePreservesOtherFontSettings(): void {
		$this->mapper->method('find')->willReturn(['id' => 'stage', ...self::validInput()]);
		$this->mapper->expects($this->once())->method('update')->with(
			'stage',
			'Stage layout',
			null,
			$this->callback(fn (array $config): bool => $config['fontSizes'] === ['title' => 16, 'scoreRow' => 10])
		)->willReturn(['id' => 'stage']);

		$this->service->patchTemplate('stage', ['fontSizes' => ['scoreRow' => 10]]);
	}

	public function testRejectsChangingTemplateId(): void {
		$this->mapper->method('find')->willReturn(['id' => 'stage', ...self::validInput()]);
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(\InvalidArgumentException::class);
		$this->service->patchTemplate('stage', ['id' => 'other']);
	}

	public function testDeleteChecksExistenceAndAuthorization(): void {
		$this->authorization->expects($this->once())
			->method('authorizePolicy')->with($this->policy, PolicyInterface::ACTION_DELETE);
		$this->mapper->method('find')->with('stage')->willReturn(['id' => 'stage', ...self::validInput()]);
		$this->mapper->expects($this->once())->method('delete')->with('stage');
		$this->service->deleteTemplate('stage');
	}

	public function testDeleteUnknownDoesNotWrite(): void {
		$this->mapper->method('find')->willReturn(null);
		$this->mapper->expects($this->never())->method('delete');
		$this->expectException(\InvalidArgumentException::class);
		$this->service->deleteTemplate('unknown');
	}
}
