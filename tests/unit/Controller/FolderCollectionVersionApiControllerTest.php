<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\FolderCollectionVersionApiController;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\FolderCollectionVersionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FolderCollectionVersionApiController.
 */
final class FolderCollectionVersionApiControllerTest extends TestCase {
	private FolderCollectionVersionService $versionService;
	private IRequest $request;
	private FolderCollectionVersionApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->versionService = $this->createMock(FolderCollectionVersionService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new FolderCollectionVersionApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->versionService
		);
	}

	public function testGetFolderCollectionVersionReturnsVersion(): void {
		$expectedVersion = [
			'id' => 1,
			'folderCollectionId' => 5,
			'validFrom' => '2024-01-01',
			'validTo' => null,
		];

		$this->versionService->expects($this->once())
			->method('getVersionById')
			->with(1)
			->willReturn($expectedVersion);

		$response = $this->controller->getFolderCollectionVersion(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedVersion, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetFolderCollectionVersionThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->versionService->expects($this->once())
			->method('getVersionById')
			->with(1)
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Not authorized');

		$this->controller->getFolderCollectionVersion(1);
	}

	public function testGetFolderCollectionVersionThrowsOCSBadRequestWhenNotFound(): void {
		$this->versionService->expects($this->once())
			->method('getVersionById')
			->with(999)
			->willThrowException(new \InvalidArgumentException('Version not found'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Version not found');

		$this->controller->getFolderCollectionVersion(999);
	}

	public function testPatchFolderCollectionVersionUpdatesVersion(): void {
		$updatedVersion = [
			'id' => 1,
			'folderCollectionId' => 5,
			'validFrom' => '2024-01-01',
			'validTo' => '2024-12-31',
		];

		$this->versionService->expects($this->once())
			->method('updateVersion')
			->with(1, '2024-12-31')
			->willReturn($updatedVersion);

		$response = $this->controller->patchFolderCollectionVersion(1, '2024-12-31');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($updatedVersion, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		// Verify that unchanged fields are present in response
		$this->assertArrayHasKey('folderCollectionId', $response->getData());
		$this->assertEquals(5, $response->getData()['folderCollectionId']);
		$this->assertArrayHasKey('validFrom', $response->getData());
		$this->assertEquals('2024-01-01', $response->getData()['validFrom']);
	}

	public function testPatchFolderCollectionVersionWithNullValidTo(): void {
		$updatedVersion = [
			'id' => 1,
			'folderCollectionId' => 5,
			'validFrom' => '2024-01-01',
			'validTo' => null,
		];

		$this->versionService->expects($this->once())
			->method('updateVersion')
			->with(1, null)
			->willReturn($updatedVersion);

		$response = $this->controller->patchFolderCollectionVersion(1, null);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($updatedVersion, $response->getData());
	}

	public function testPatchFolderCollectionVersionThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->versionService->expects($this->once())
			->method('updateVersion')
			->with(1, '2024-12-31')
			->willThrowException(new PermissionDeniedException('Cannot update version'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot update version');

		$this->controller->patchFolderCollectionVersion(1, '2024-12-31');
	}

	public function testPatchFolderCollectionVersionThrowsOCSBadRequestOnInvalidDate(): void {
		$this->versionService->expects($this->once())
			->method('updateVersion')
			->with(1, 'invalid-date')
			->willThrowException(new \InvalidArgumentException('Invalid date format'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Invalid date format');

		$this->controller->patchFolderCollectionVersion(1, 'invalid-date');
	}

	public function testPatchFolderCollectionVersionThrowsOCSBadRequestWhenVersionNotActive(): void {
		$this->versionService->expects($this->once())
			->method('updateVersion')
			->with(1, '2024-12-31')
			->willThrowException(new \InvalidArgumentException('Cannot modify non-active version'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Cannot modify non-active version');

		$this->controller->patchFolderCollectionVersion(1, '2024-12-31');
	}
}
