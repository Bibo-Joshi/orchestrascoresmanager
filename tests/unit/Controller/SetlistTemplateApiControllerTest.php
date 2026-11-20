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

	public function testEmptyFontSizesSerializeAsJsonObjectInList(): void {
		$this->service->method('getTemplates')->willReturn([
			['id' => 'empty', 'fontSizes' => [], 'rules' => []],
			['id' => 'configured', 'fontSizes' => ['title' => 18], 'rules' => []],
		]);

		$data = $this->controller->getTemplates()->getData();
		$this->assertSame('{}', json_encode($data[0]['fontSizes'], JSON_THROW_ON_ERROR));
		$this->assertSame('[]', json_encode($data[0]['rules'], JSON_THROW_ON_ERROR));
		$this->assertSame('{"title":18}', json_encode($data[1]['fontSizes'], JSON_THROW_ON_ERROR));
	}

	public function testEmptyFontSizesSerializeAsJsonObjectAfterCreateAndPatch(): void {
		$this->service->method('createTemplate')->willReturn(['id' => 'empty', 'fontSizes' => []]);
		$this->service->method('patchTemplate')->willReturn(['id' => 'empty', 'fontSizes' => []]);

		$created = $this->controller->postTemplate(['name' => 'Empty'])->getData();
		$updated = $this->controller->patchTemplate('empty', ['name' => 'Updated'])->getData();
		$this->assertSame('{}', json_encode($created['fontSizes'], JSON_THROW_ON_ERROR));
		$this->assertSame('{}', json_encode($updated['fontSizes'], JSON_THROW_ON_ERROR));
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
