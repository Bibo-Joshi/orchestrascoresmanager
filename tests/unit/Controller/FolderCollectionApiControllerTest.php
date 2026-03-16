<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\FolderCollectionApiController;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\FolderCollectionVersionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FolderCollectionApiController.
 */
final class FolderCollectionApiControllerTest extends TestCase {
	private FolderCollectionService $folderCollectionService;
	private FolderCollectionVersionService $versionService;
	private IRequest $request;
	private FolderCollectionApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->folderCollectionService = $this->createMock(FolderCollectionService::class);
		$this->versionService = $this->createMock(FolderCollectionVersionService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new FolderCollectionApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->folderCollectionService,
			$this->versionService
		);
	}

	public function testGetFolderCollectionsReturnsAllCollections(): void {
		$expectedCollections = [
			['id' => 1, 'title' => 'Collection 1', 'collectionType' => 'alphabetical'],
			['id' => 2, 'title' => 'Collection 2', 'collectionType' => 'indexed'],
		];

		$this->folderCollectionService->expects($this->once())
			->method('getAllFolderCollections')
			->willReturn($expectedCollections);

		$response = $this->controller->getFolderCollections();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedCollections, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetFolderCollectionsThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->folderCollectionService->expects($this->once())
			->method('getAllFolderCollections')
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Not authorized');

		$this->controller->getFolderCollections();
	}

	public function testGetFolderCollectionReturnsSpecificCollection(): void {
		$expectedCollection = ['id' => 1, 'title' => 'Collection 1', 'collectionType' => 'alphabetical'];

		$this->folderCollectionService->expects($this->once())
			->method('getFolderCollectionById')
			->with(1)
			->willReturn($expectedCollection);

		$response = $this->controller->getFolderCollection(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedCollection, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetFolderCollectionThrowsOCSBadRequestWhenNotFound(): void {
		$this->folderCollectionService->expects($this->once())
			->method('getFolderCollectionById')
			->with(999)
			->willThrowException(new \InvalidArgumentException('Collection not found'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Collection not found');

		$this->controller->getFolderCollection(999);
	}

	public function testPostFolderCollectionCreatesNewCollection(): void {
		$collectionData = ['id' => 1, 'title' => 'New Collection', 'collectionType' => 'alphabetical'];

		$this->folderCollectionService->expects($this->once())
			->method('createFolderCollection')
			->with($this->isInstanceOf(FolderCollection::class), null)
			->willReturn($collectionData);

		$response = $this->controller->postFolderCollection(
			title: 'New Collection',
			collectionType: 'alphabetical'
		);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($collectionData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostFolderCollectionWithValidFrom(): void {
		$collectionData = ['id' => 1, 'title' => 'New Collection', 'collectionType' => 'indexed'];

		$this->folderCollectionService->expects($this->once())
			->method('createFolderCollection')
			->with($this->isInstanceOf(FolderCollection::class), '2024-01-01')
			->willReturn($collectionData);

		$response = $this->controller->postFolderCollection(
			title: 'New Collection',
			collectionType: 'indexed',
			validFrom: '2024-01-01'
		);

		$this->assertEquals($collectionData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostFolderCollectionThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->folderCollectionService->expects($this->once())
			->method('createFolderCollection')
			->willThrowException(new PermissionDeniedException('Cannot create collection'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot create collection');

		$this->controller->postFolderCollection(
			title: 'Test',
			collectionType: 'alphabetical'
		);
	}

	public function testPostFolderCollectionThrowsOCSBadRequestOnInvalidType(): void {
		$this->folderCollectionService->expects($this->once())
			->method('createFolderCollection')
			->willThrowException(new \InvalidArgumentException('Invalid collection type'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Invalid collection type');

		$this->controller->postFolderCollection(
			title: 'Test',
			collectionType: 'invalid'
		);
	}

	public function testPatchFolderCollectionUpdatesCollection(): void {
		$folderCollection = new FolderCollection();
		$folderCollection->setId(1);
		$folderCollection->setTitle('Original');
		$folderCollection->setCollectionType('alphabetical');
		$folderCollection->setDescription('Original description');

		$updatedCollection = [
			'id' => 1,
			'title' => 'Updated',
			'collectionType' => 'alphabetical',
			'description' => 'Original description',
			'scoreCount' => 5,
		];

		$this->folderCollectionService->expects($this->once())
			->method('findFolderCollectionEntity')
			->with(1)
			->willReturn($folderCollection);

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn(['id' => 1, 'title' => 'Updated']);

		$this->folderCollectionService->expects($this->once())
			->method('updateFolderCollection')
			->willReturn($updatedCollection);

		$response = $this->controller->patchFolderCollection(id: 1, title: 'Updated');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($updatedCollection, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		// Verify that unchanged fields are present in response
		$this->assertArrayHasKey('collectionType', $response->getData());
		$this->assertEquals('alphabetical', $response->getData()['collectionType']);
		$this->assertArrayHasKey('description', $response->getData());
		$this->assertEquals('Original description', $response->getData()['description']);
	}

	public function testPatchFolderCollectionThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->folderCollectionService->expects($this->once())
			->method('findFolderCollectionEntity')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot update collection'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot update collection');

		$this->controller->patchFolderCollection(id: 1, title: 'New Title');
	}

	public function testDeleteFolderCollectionRemovesCollection(): void {
		$this->folderCollectionService->expects($this->once())
			->method('deleteFolderCollection')
			->with(1);

		$response = $this->controller->deleteFolderCollection(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteFolderCollectionThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->folderCollectionService->expects($this->once())
			->method('deleteFolderCollection')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot delete collection'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot delete collection');

		$this->controller->deleteFolderCollection(1);
	}

	#[TestWith([null])]
	#[TestWith([5])]
	public function testGetFolderCollectionScoresReturnsScores(?int $versionId): void {
		$expectedScores = [
			['id' => 1, 'title' => 'Score 1'],
			['id' => 2, 'title' => 'Score 2'],
		];

		$this->folderCollectionService->expects($this->once())
			->method('getScoresInFolderCollection')
			->with(1, $versionId)
			->willReturn($expectedScores);

		$response = $this->controller->getFolderCollectionScores(1, $versionId);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScores, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testPostFolderCollectionScoreAddsScore(): void {
		$this->folderCollectionService->expects($this->once())
			->method('addScoreToFolderCollection')
			->with(10, 1, null);

		$response = $this->controller->postFolderCollectionScore(id: 1, scoreId: 10);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostFolderCollectionScoreWithIndex(): void {
		$this->folderCollectionService->expects($this->once())
			->method('addScoreToFolderCollection')
			->with(10, 1, 5);

		$response = $this->controller->postFolderCollectionScore(id: 1, scoreId: 10, index: 5);

		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostFolderCollectionScoreThrowsOCSBadRequestWhenIndexRequired(): void {
		$this->folderCollectionService->expects($this->once())
			->method('addScoreToFolderCollection')
			->willThrowException(new \InvalidArgumentException('Index required for indexed collection'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Index required for indexed collection');

		$this->controller->postFolderCollectionScore(id: 1, scoreId: 10);
	}

	public function testDeleteFolderCollectionScoreRemovesScore(): void {
		$this->folderCollectionService->expects($this->once())
			->method('removeScoreFromFolderCollection')
			->with(10, 1);

		$response = $this->controller->deleteFolderCollectionScore(id: 1, scoreId: 10);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteFolderCollectionScoreThrowsOCSBadRequestWhenScoreInViaBook(): void {
		$this->folderCollectionService->expects($this->once())
			->method('removeScoreFromFolderCollection')
			->willThrowException(new \InvalidArgumentException('Score in collection via book, remove book instead'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Score in collection via book, remove book instead');

		$this->controller->deleteFolderCollectionScore(id: 1, scoreId: 10);
	}

	public function testGetFolderCollectionScoreBooksReturnsScoreBooks(): void {
		$expectedBooks = [
			['id' => 1, 'title' => 'Book 1'],
			['id' => 2, 'title' => 'Book 2'],
		];

		$this->folderCollectionService->expects($this->once())
			->method('getScoreBooksInFolderCollection')
			->with(1, null)
			->willReturn($expectedBooks);

		$response = $this->controller->getFolderCollectionScoreBooks(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedBooks, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetFolderCollectionVersionsReturnsVersions(): void {
		$expectedVersions = [
			['id' => 1, 'validFrom' => '2024-01-01', 'validTo' => null],
		];

		$this->versionService->expects($this->once())
			->method('getVersionsForFolderCollection')
			->with(1)
			->willReturn($expectedVersions);

		$response = $this->controller->getFolderCollectionVersions(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedVersions, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testPostFolderCollectionVersionCreatesVersion(): void {
		$versionData = ['id' => 2, 'validFrom' => '2024-06-01', 'validTo' => null];

		$this->versionService->expects($this->once())
			->method('createVersion')
			->with(1, '2024-06-01')
			->willReturn($versionData);

		$response = $this->controller->postFolderCollectionVersion(id: 1, validFrom: '2024-06-01');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($versionData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostFolderCollectionVersionThrowsOCSBadRequestOnOverlap(): void {
		$this->versionService->expects($this->once())
			->method('createVersion')
			->willThrowException(new \InvalidArgumentException('Version overlaps with existing version'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Version overlaps with existing version');

		$this->controller->postFolderCollectionVersion(id: 1, validFrom: '2024-06-01');
	}

	#[TestWith([null])]
	#[TestWith([10])]
	public function testPostFolderCollectionScoreBookAddsScoreBook(?int $index): void {
		$this->folderCollectionService->expects($this->once())
			->method('addScoreBookToFolderCollection')
			->with(5, 1, $index);

		$response = $this->controller->postFolderCollectionScoreBook(id: 1, scoreBookId: 5, index: $index);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testDeleteFolderCollectionScoreBookRemovesScoreBook(): void {
		$this->folderCollectionService->expects($this->once())
			->method('removeScoreBookFromFolderCollection')
			->with(5, 1);

		$response = $this->controller->deleteFolderCollectionScoreBook(id: 1, scoreBookId: 5);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith(['2024-07-01'])]
	#[TestWith([null])]
	public function testStartNewVersionCreatesNewVersion(?string $validFrom): void {
		$expectedValidFrom = $validFrom ?? date('Y-m-d');
		$versionData = ['id' => 3, 'validFrom' => $expectedValidFrom, 'validTo' => null];

		$this->versionService->expects($this->once())
			->method('startNewVersion')
			->with(1, $validFrom)
			->willReturn($versionData);

		$response = $this->controller->startNewVersion(id: 1, validFrom: $validFrom);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($versionData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}
}
