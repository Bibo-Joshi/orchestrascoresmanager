<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Controller\SetlistTemplateApiController;
use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\SetlistTemplateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateApiControllerTest extends TestCase {
	private SetlistTemplateService $service;
	private SetlistTemplateApiController $controller;

	protected function setUp(): void {
		$this->service = $this->createMock(SetlistTemplateService::class);
		$this->controller = new SetlistTemplateApiController(
			'orchestrascoresmanager',
			$this->createMock(IRequest::class),
			$this->service,
		);
	}

	private static function template(int $id = 1, array $fontSizes = []): SetlistTemplate {
		$now = new DateTimeImmutable('2026-11-18 12:00:00');

		return SetlistTemplate::fromParams([
			'id' => $id,
			'name' => 'Stage',
			'configJson' => json_encode([
				'fontSizes' => $fontSizes,
				'margin' => 8,
				'cellPadding' => 1.5,
				'rules' => [],
			], JSON_THROW_ON_ERROR),
			'createdBy' => 'editor',
			'createdAt' => $now,
			'updatedAt' => $now,
		]);
	}

	public function testGetTemplatesReturnsSharedList(): void {
		$templates = [self::template()];

		$this->service->expects($this->once())
			->method('getTemplates')
			->willReturn($templates);

		$result = $this->controller->getTemplates();

		$this->assertSame(Http::STATUS_OK, $result->getStatus());
		$this->assertSame($templates, $result->getData());
	}

	public function testPostTemplateCreates(): void {
		$payload = ['name' => 'Stage'];
		$template = self::template();

		$this->service->expects($this->once())
			->method('createTemplate')
			->with($payload)
			->willReturn($template);

		$result = $this->controller->postTemplate($payload);

		$this->assertSame(Http::STATUS_CREATED, $result->getStatus());
		$this->assertSame($template, $result->getData());
	}

	public function testPatchTemplateUpdates(): void {
		$template = self::template();

		$this->service->expects($this->once())
			->method('patchTemplate')
			->with(1, ['margin' => 8])
			->willReturn($template);

		$result = $this->controller->patchTemplate(1, ['margin' => 8]);

		$this->assertSame(Http::STATUS_OK, $result->getStatus());
		$this->assertSame($template, $result->getData());
	}

	public function testDeleteTemplateRemoves(): void {
		$this->service->expects($this->once())
			->method('deleteTemplate')
			->with(1);

		$this->assertSame([], $this->controller->deleteTemplate(1)->getData());
	}

	public function testEmptyFontSizesSerializeAsNull(): void {
		$template = self::template();

		$this->assertNull($template->jsonSerialize()['fontSizes']);
		$this->assertSame([], $template->jsonSerialize()['rules']);
	}

	public function testConfiguredFontSizesArePreserved(): void {
		$template = self::template(1, ['title' => 18]);

		$this->assertSame(
			['title' => 18],
			$template->jsonSerialize()['fontSizes']
		);
	}

	public function testForbidsUnauthorizedPost(): void {
		$this->service->method('createTemplate')
			->willThrowException(new PermissionDeniedException('Denied'));

		$this->expectException(OCSForbiddenException::class);
		$this->controller->postTemplate(['name' => 'Stage']);
	}

	public function testRejectsBadPayload(): void {
		$this->service->method('patchTemplate')
			->willThrowException(new \InvalidArgumentException('Bad font'));

		$this->expectException(OCSBadRequestException::class);
		$this->controller->patchTemplate(1, ['fontSizes' => ['title' => 'huge']]);
	}
}
