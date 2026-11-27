<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\ConductorsScoreVariantApiController;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Service\ConductorsScoreVariantService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConductorsScoreVariantApiController.
 */
final class ConductorsScoreVariantApiControllerTest extends TestCase {
	private ConductorsScoreVariantService&MockObject $service;
	private ConductorsScoreVariantApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(ConductorsScoreVariantService::class);
		$this->controller = new ConductorsScoreVariantApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetListWrapsVariants(): void {
		$variant = new ConductorsScoreVariant();
		$this->service->method('getVariantsForConductorsScore')->with(2)->willReturn([$variant]);

		$this->assertSame(['variants' => [$variant]], $this->controller->getConductorsScoreVariants(2)->getData());
	}

	public function testPostReturnsCreated(): void {
		$variant = new ConductorsScoreVariant();
		$this->service->expects($this->once())->method('createConductorsScoreVariant')->with(2, 'full', 'd')->willReturn($variant);

		$response = $this->controller->postConductorsScoreVariant(2, 'full', 'd');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($variant, $response->getData());
	}

	public function testPatchLoadsEntityAndUpdates(): void {
		$variant = new ConductorsScoreVariant();
		$this->service->method('findConductorsScoreVariantEntity')->with(5)->willReturn($variant);
		$this->service->expects($this->once())->method('updateConductorsScoreVariant')->with($variant, 'condensed', null)->willReturn(['id' => 5]);

		$this->assertSame(['id' => 5], $this->controller->patchConductorsScoreVariant(5, 'condensed')->getData());
	}

	public function testGetAndDeleteSuccess(): void {
		$this->service->method('getConductorsScoreVariantById')->with(5)->willReturn(['id' => 5]);
		$this->service->expects($this->once())->method('deleteConductorsScoreVariant')->with(5);

		$this->assertSame(['id' => 5], $this->controller->getConductorsScoreVariant(5)->getData());
		$this->assertSame([], $this->controller->deleteConductorsScoreVariant(5)->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testExceptionsAreBridged(string $serviceException, string $expected): void {
		$this->service->method('createConductorsScoreVariant')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->postConductorsScoreVariant(2, 'full');
	}
}
