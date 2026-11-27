<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\PartVariantApiController;
use OCA\OrchestraScoresManager\Db\PartVariant;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\PartVariantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PartVariantApiController.
 */
final class PartVariantApiControllerTest extends TestCase {
	private PartVariantService&MockObject $service;
	private PartVariantApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(PartVariantService::class);
		$this->controller = new PartVariantApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetListWrapsVariants(): void {
		$variant = new PartVariant();
		$this->service->method('getVariantsForPart')->with(3)->willReturn([$variant]);

		$response = $this->controller->getPartVariants(3);

		$this->assertSame(['variants' => [$variant]], $response->getData());
	}

	public function testPostPassesNotationAndReturnsCreated(): void {
		$variant = new PartVariant();
		$clef = ['sign' => 'G', 'line' => 2];
		$transposition = ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0];
		$this->service->expects($this->once())->method('createPartVariant')
			->with(3, null, $clef, $transposition, 'd')->willReturn($variant);

		$response = $this->controller->postPartVariant(3, null, $clef, $transposition, 'd');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	/**
	 * @param array<array-key, mixed> $clef
	 * @param array<array-key, mixed> $transposition
	 */
	#[TestWith([['sign' => 5], null])]
	#[TestWith([['id' => 'x'], null])]
	#[TestWith([null, ['diatonic' => 'a', 'chromatic' => 0, 'octave' => 0]])]
	public function testPostRejectsWrongTypes(?array $clef, ?array $transposition): void {
		$this->service->expects($this->never())->method('createPartVariant');
		$this->expectException(OCSBadRequestException::class);
		$this->controller->postPartVariant(3, null, $clef, $transposition);
	}

	public function testPatchLoadsEntityAndReturnsUpdate(): void {
		$variant = new PartVariant();
		$this->service->method('findPartVariantEntity')->with(5)->willReturn($variant);
		$this->service->expects($this->once())->method('updatePartVariant')
			->with($variant, 7, null, null, null)->willReturn(['id' => 5]);

		$response = $this->controller->patchPartVariant(5, 7);

		$this->assertSame(['id' => 5], $response->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testDeleteBridgesExceptions(string $serviceException, string $expected): void {
		$this->service->method('deletePartVariant')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->deletePartVariant(5);
	}
}
