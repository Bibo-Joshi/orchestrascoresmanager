<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\SetlistTemplateService;
use OCA\OrchestraScoresManager\Service\SetlistTemplateValidator;
use OCP\AppFramework\Db\DoesNotExistException;
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

	private static function savedTemplate(): SetlistTemplate {
		$now = new DateTimeImmutable('2026-11-18 12:00:00');

		return SetlistTemplate::fromParams([
			'id' => 1,
			'name' => 'Stage layout',
			'description' => null,
			'configJson' => '{"fontSizes":{"title":16,"scoreRow":8},"margin":8,"cellPadding":1.5,"rules":[]}',
			'createdBy' => 'editor',
			'createdAt' => $now,
			'updatedAt' => $now,
		]);
	}

	public function testGetTemplatesAuthorizesRead(): void {
		$expected = [self::savedTemplate()];

		$this->authorization->expects($this->once())
			->method('authorizePolicy')
			->with($this->policy, PolicyInterface::ACTION_READ);

		$this->mapper->expects($this->once())
			->method('findAll')
			->willReturn($expected);

		$this->assertSame($expected, $this->service->getTemplates());
	}

	public function testCreateValidatesAndPersistsWithCreator(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('editor');
		$this->session->method('getUser')->willReturn($user);

		$this->authorization->expects($this->once())
			->method('authorizePolicy')
			->with($this->policy, PolicyInterface::ACTION_CREATE);

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (SetlistTemplate $template): SetlistTemplate {
				$this->assertSame('Stage layout', $template->getName());
				$this->assertSame('editor', $template->getCreatedBy());
				$this->assertNotNull($template->getCreatedAt());
				$this->assertEquals($template->getCreatedAt(), $template->getUpdatedAt());

				$template->setId(1);
				return $template;
			});

		$this->assertSame(1, $this->service->createTemplate(self::validInput())->getId());
	}

	public function testPatchMergesAndValidates(): void {
		$template = self::savedTemplate();

		$this->authorization->expects($this->once())
			->method('authorizePolicy')
			->with($this->policy, PolicyInterface::ACTION_UPDATE);

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->once())
			->method('update')->with($template)->willReturn($template);

		$updated = $this->service->patchTemplate(1, ['margin' => 12]);

		$this->assertEquals(12, $updated->jsonSerialize()['margin']);
		$this->assertSame('Stage layout', $updated->getName());
		$this->assertSame('editor', $updated->getCreatedBy());
	}

	public function testPatchOneFontSizePreservesOtherFontSettings(): void {
		$template = self::savedTemplate();

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->once())
			->method('update')->willReturn($template);

		$updated = $this->service->patchTemplate(1, [
			'fontSizes' => ['scoreRow' => 10],
		]);

		$this->assertSame(
			['title' => 16, 'scoreRow' => 10],
			$updated->jsonSerialize()['fontSizes']
		);
	}

	public function testPatchNullFontSizesResetsToDefaults(): void {
		$template = self::savedTemplate();

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->once())
			->method('update')->willReturn($template);

		$updated = $this->service->patchTemplate(1, ['fontSizes' => null]);

		$this->assertNull($updated->jsonSerialize()['fontSizes']);
	}

	public function testRejectsChangingTemplateId(): void {
		$this->mapper->method('find')->willReturn(self::savedTemplate());
		$this->mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patchTemplate(1, ['id' => 2]);
	}

	public function testDeleteChecksExistenceAndAuthorization(): void {
		$template = self::savedTemplate();

		$this->authorization->expects($this->once())
			->method('authorizePolicy')
			->with($this->policy, PolicyInterface::ACTION_DELETE);

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->once())
			->method('deleteAndClearDefaults')->with($template);

		$this->service->deleteTemplate(1);
	}

	public function testDeleteUnknownDoesNotWrite(): void {
		$this->mapper->method('find')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->mapper->expects($this->never())->method('deleteAndClearDefaults');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->deleteTemplate(999999);
	}

	public function testCreateRejectsMissingAuthenticatedUser(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('User not authenticated');

		$this->service->createTemplate(self::validInput());
	}

	public function testPatchRejectsMalformedStoredFontSizes(): void {
		$template = self::savedTemplate();
		$template->setConfigJson('{"fontSizes":"invalid","margin":8,"cellPadding":1.5,"rules":[]}');

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->never())->method('update');

		$this->expectException(\UnexpectedValueException::class);
		$this->service->patchTemplate(1, ['fontSizes' => ['title' => 20]]);
	}

	public function testPatchRejectsNonObjectConfiguration(): void {
		$template = self::savedTemplate();
		$template->setConfigJson('[]');

		$this->mapper->method('find')->with(1)->willReturn($template);
		$this->mapper->expects($this->never())->method('update');

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('Stored PDF template configuration must be a JSON object');

		$this->service->patchTemplate(1, ['margin' => 10]);
	}

}
