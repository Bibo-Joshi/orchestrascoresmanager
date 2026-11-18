<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\SetlistTemplateApiController;
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

	public function testGetTemplatesReturnsSharedList(): void {
		$this->service->expects($this->once())->method('getTemplates')
			->willReturn([['id' => 'stage', 'name' => 'Stage']]);
		$result = $this->controller->getTemplates();
		$this->assertSame(Http::STATUS_OK, $result->getStatus());
		$this->assertSame([['id' => 'stage', 'name' => 'Stage']], $result->getData());
	}

	public function testPostTemplateCreates(): void {
		$payload = ['name' => 'Stage'];
		$this->service->expects($this->once())->method('createTemplate')->with($payload)
			->willReturn(['id' => 'uuid', 'name' => 'Stage']);
		$result = $this->controller->postTemplate($payload);
		$this->assertSame(Http::STATUS_CREATED, $result->getStatus());
		$this->assertSame('uuid', $result->getData()['id']);
	}

	public function testPatchTemplateUpdates(): void {
		$this->service->expects($this->once())->method('patchTemplate')->with('stage', ['margin' => 8])
			->willReturn(['id' => 'stage', 'margin' => 8]);
		$this->assertSame(8, $this->controller->patchTemplate('stage', ['margin' => 8])->getData()['margin']);
	}

	public function testDeleteTemplateRemoves(): void {
		$this->service->expects($this->once())->method('deleteTemplate')->with('stage');
		$this->assertSame([], $this->controller->deleteTemplate('stage')->getData());
	}

	public function testForbidsUnauthorizedPost(): void {
		$this->service->method('createTemplate')->willThrowException(new PermissionDeniedException('Denied'));
		$this->expectException(OCSForbiddenException::class);
		$this->controller->postTemplate(['name' => 'Stage']);
	}

	public function testRejectsBadPayload(): void {
		$this->service->method('patchTemplate')->willThrowException(new \InvalidArgumentException('Bad font'));
		$this->expectException(OCSBadRequestException::class);
		$this->controller->patchTemplate('stage', ['fontSizes' => ['title' => 'huge']]);
	}
}
