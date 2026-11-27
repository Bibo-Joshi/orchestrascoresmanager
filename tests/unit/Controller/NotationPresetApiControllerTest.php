<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Controller\NotationPresetApiController;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\NotationPresetService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotationPresetApiController.
 */
final class NotationPresetApiControllerTest extends TestCase {
	private const T = ['diatonic' => -1, 'chromatic' => -2, 'octave' => 0];

	private NotationPresetService&MockObject $service;
	private NotationPresetApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(NotationPresetService::class);
		$this->controller = new NotationPresetApiController('orchestrascoresmanager', $this->createMock(IRequest::class), $this->service);
	}

	public function testGetListWrapsPresets(): void {
		$preset = new NotationPreset();
		$this->service->method('getAllNotationPresets')->willReturn([$preset]);

		$this->assertSame(['notationPresets' => [$preset]], $this->controller->getNotationPresets()->getData());
	}

	public function testPostReturnsCreated(): void {
		$preset = new NotationPreset();
		$this->service->expects($this->once())->method('createNotationPreset')->with(1, self::T, 'B flat', null)->willReturn($preset);

		$response = $this->controller->postNotationPreset(1, self::T, 'B flat');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostRejectsWrongTranspositionTypes(): void {
		$this->service->expects($this->never())->method('createNotationPreset');
		$this->expectException(OCSBadRequestException::class);
		$this->controller->postNotationPreset(1, ['diatonic' => 'x', 'chromatic' => 0, 'octave' => 0], 'B flat');
	}

	public function testPatchRejectsWrongTranspositionTypes(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->controller->patchNotationPreset(1, null, ['diatonic' => 1.5, 'chromatic' => 0, 'octave' => 0]);
	}

	public function testPatchLoadsEntityAndUpdates(): void {
		$preset = new NotationPreset();
		$this->service->method('findNotationPresetEntity')->with(5)->willReturn($preset);
		$this->service->expects($this->once())->method('updateNotationPreset')->with($preset, 2, self::T, null, null)->willReturn(['id' => 5]);

		$this->assertSame(['id' => 5], $this->controller->patchNotationPreset(5, 2, self::T)->getData());
	}

	public function testGetAndDeleteSuccess(): void {
		$this->service->method('getNotationPresetById')->with(5)->willReturn(['id' => 5]);
		$this->service->expects($this->once())->method('deleteNotationPreset')->with(5);

		$this->assertSame(['id' => 5], $this->controller->getNotationPreset(5)->getData());
		$this->assertSame([], $this->controller->deleteNotationPreset(5)->getData());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class])]
	#[TestWith([InvalidArgumentException::class, OCSBadRequestException::class])]
	public function testExceptionsAreBridged(string $serviceException, string $expected): void {
		$this->service->method('deleteNotationPreset')->willThrowException(new $serviceException('msg'));
		$this->expectException($expected);
		$this->controller->deleteNotationPreset(5);
	}
}
