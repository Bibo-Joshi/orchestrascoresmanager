<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\ClefApiController;
use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Service\ClefService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ClefApiController.
 */
final class ClefApiControllerTest extends TestCase {
	private ClefService&MockObject $service;
	private ClefApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(ClefService::class);
		$this->controller = new ClefApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetClefsWrapsList(): void {
		$clef = new Clef();
		$this->service->method('getAllClefs')->willReturn([$clef]);

		$this->assertSame(['clefs' => [$clef]], $this->controller->getClefs()->getData());
	}

	public function testPostClefReturnsCreated(): void {
		$clef = new Clef();
		$this->service->expects($this->once())->method('findOrCreateClef')->with('G', 2, 0, 'T')->willReturn($clef);

		$response = $this->controller->postClef('G', 2, 0, 'T');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($clef, $response->getData());
	}

	public function testPatchLoadsEntityAndUpdates(): void {
		$clef = new Clef();
		$this->service->method('findClefEntity')->with(3)->willReturn($clef);
		$this->service->expects($this->once())->method('updateClef')->with($clef, 'F', 4, null, null)->willReturn(['id' => 3]);

		$this->assertSame(['id' => 3], $this->controller->patchClef(3, 'F', 4)->getData());
	}

	public function testGetAndDeleteSuccess(): void {
		$this->service->method('getClefById')->with(3)->willReturn(['id' => 3]);
		$this->service->expects($this->once())->method('deleteClef')->with(3);

		$this->assertSame(['id' => 3], $this->controller->getClef(3)->getData());
		$this->assertSame([], $this->controller->deleteClef(3)->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testExceptionsAreBridged(string $serviceException, string $expected): void {
		$this->service->method('deleteClef')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->deleteClef(3);
	}
}
