<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\PartApiController;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\PartService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PartApiController.
 */
final class PartApiControllerTest extends TestCase {
	private PartService&MockObject $service;
	private PartApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(PartService::class);
		$this->controller = new PartApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetScorePartsWrapsList(): void {
		$part = new Part();
		$this->service->method('getPartsForScore')->with(2)->willReturn([$part]);

		$this->assertSame(['parts' => [$part]], $this->controller->getScoreParts(2)->getData());
	}

	public function testPostReturnsCreated(): void {
		$part = new Part();
		$this->service->expects($this->once())->method('createPart')->with(2, 'Violin', 'd')->willReturn($part);

		$response = $this->controller->postScorePart(2, 'Violin', 'd');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($part, $response->getData());
	}

	public function testPatchLoadsEntityAndUpdates(): void {
		$part = new Part();
		$this->service->method('findPartEntity')->with(5)->willReturn($part);
		$this->service->expects($this->once())->method('updatePart')->with($part, 'New', null)->willReturn(['id' => 5]);

		$this->assertSame(['id' => 5], $this->controller->patchPart(5, 'New')->getData());
	}

	public function testGetAndDeleteSuccess(): void {
		$this->service->method('getPartById')->with(5)->willReturn(['id' => 5]);
		$this->service->expects($this->once())->method('deletePart')->with(5);

		$this->assertSame(['id' => 5], $this->controller->getPart(5)->getData());
		$this->assertSame([], $this->controller->deletePart(5)->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testExceptionsAreBridged(string $serviceException, string $expected): void {
		$this->service->method('createPart')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->postScorePart(2, 'Violin');
	}
}
