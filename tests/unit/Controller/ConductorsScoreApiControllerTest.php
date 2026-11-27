<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\ConductorsScoreApiController;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Service\ConductorsScoreService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConductorsScoreApiController.
 */
final class ConductorsScoreApiControllerTest extends TestCase {
	private ConductorsScoreService&MockObject $service;
	private ConductorsScoreApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(ConductorsScoreService::class);
		$this->controller = new ConductorsScoreApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetReturnsConductorsScoreOrNull(): void {
		$cs = new ConductorsScore();
		$this->service->method('getConductorsScoreForScore')->willReturnOnConsecutiveCalls($cs, null);

		$this->assertSame(['conductorsScore' => $cs], $this->controller->getScoreConductorsScore(2)->getData());
		$this->assertSame(['conductorsScore' => null], $this->controller->getScoreConductorsScore(2)->getData());
	}

	public function testPostReturnsCreated(): void {
		$cs = new ConductorsScore();
		$this->service->expects($this->once())->method('createConductorsScore')->with(2)->willReturn($cs);

		$response = $this->controller->postScoreConductorsScore(2);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($cs, $response->getData());
	}

	public function testDeleteSuccess(): void {
		$this->service->expects($this->once())->method('deleteConductorsScore')->with(4);

		$this->assertSame([], $this->controller->deleteConductorsScore(4)->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testExceptionsAreBridged(string $serviceException, string $expected): void {
		$this->service->method('createConductorsScore')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->postScoreConductorsScore(2);
	}
}
