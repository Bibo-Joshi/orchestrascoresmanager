<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\ScoreBookApiController;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\ScoreBookService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreBookApiController.
 */
final class ScoreBookApiControllerTest extends TestCase {
	private ScoreBookService $scoreBookService;
	private FolderCollectionService $folderCollectionService;
	private IRequest $request;
	private ScoreBookApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreBookService = $this->createMock(ScoreBookService::class);
		$this->folderCollectionService = $this->createMock(FolderCollectionService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new ScoreBookApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->scoreBookService,
			$this->folderCollectionService
		);
	}

	public function testGetScoreBooksReturnsAllScoreBooks(): void {
		$expectedScoreBooks = [
			['id' => 1, 'title' => 'Book 1'],
			['id' => 2, 'title' => 'Book 2'],
		];

		$this->scoreBookService->expects($this->once())
			->method('getAllScoreBooks')
			->willReturn($expectedScoreBooks);

		$response = $this->controller->getScoreBooks();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScoreBooks, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoreBooksThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreBookService->expects($this->once())
			->method('getAllScoreBooks')
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Not authorized');

		$this->controller->getScoreBooks();
	}

	public function testGetScoreBookReturnsSpecificScoreBook(): void {
		$expectedScoreBook = ['id' => 1, 'title' => 'Score Book 1'];

		$this->scoreBookService->expects($this->once())
			->method('getScoreBookById')
			->with(1)
			->willReturn($expectedScoreBook);

		$response = $this->controller->getScoreBook(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScoreBook, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoreBookThrowsOCSBadRequestWhenNotFound(): void {
		$this->scoreBookService->expects($this->once())
			->method('getScoreBookById')
			->with(999)
			->willThrowException(new \InvalidArgumentException('Score book not found'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Score book not found');

		$this->controller->getScoreBook(999);
	}

	public function testPostScoreBookCreatesNewScoreBook(): void {
		$scoreBookData = ['id' => 1, 'title' => 'New Book'];

		$this->scoreBookService->expects($this->once())
			->method('createScoreBook')
			->with($this->isInstanceOf(ScoreBook::class), null)
			->willReturn($scoreBookData);

		$response = $this->controller->postScoreBook(title: 'New Book');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($scoreBookData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreBookWithTagIds(): void {
		$scoreBookData = ['id' => 1, 'title' => 'New Book'];
		$tagIds = [1, 2, 3];

		$this->scoreBookService->expects($this->once())
			->method('createScoreBook')
			->with($this->isInstanceOf(ScoreBook::class), $tagIds)
			->willReturn($scoreBookData);

		$response = $this->controller->postScoreBook(
			title: 'New Book',
			tagIds: $tagIds
		);

		$this->assertEquals($scoreBookData, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreBookThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreBookService->expects($this->once())
			->method('createScoreBook')
			->willThrowException(new PermissionDeniedException('Cannot create score book'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot create score book');

		$this->controller->postScoreBook(title: 'Test');
	}

	public function testPatchScoreBookUpdatesExistingScoreBook(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$scoreBook->setTitle('Original');
		$scoreBook->setComposer('Bach');
		$scoreBook->setYear(2020);

		$updatedScoreBook = [
			'id' => 1,
			'title' => 'Updated',
			'composer' => 'Bach',
			'year' => 2020,
			'scoreCount' => 5,
		];

		$this->scoreBookService->expects($this->once())
			->method('findScoreBookEntity')
			->with(1)
			->willReturn($scoreBook);

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn(['id' => 1, 'title' => 'Updated']);

		$this->scoreBookService->expects($this->once())
			->method('updateScoreBook')
			->willReturn($updatedScoreBook);

		$response = $this->controller->patchScoreBook(id: 1, title: 'Updated');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($updatedScoreBook, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		// Verify that unchanged fields are present in response
		$this->assertArrayHasKey('composer', $response->getData());
		$this->assertEquals('Bach', $response->getData()['composer']);
		$this->assertArrayHasKey('year', $response->getData());
		$this->assertEquals(2020, $response->getData()['year']);
	}

	public function testPatchScoreBookThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreBookService->expects($this->once())
			->method('findScoreBookEntity')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot update score book'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot update score book');

		$this->controller->patchScoreBook(id: 1, title: 'New Title');
	}

	public function testDeleteScoreBookRemovesScoreBook(): void {
		$this->scoreBookService->expects($this->once())
			->method('deleteScoreBook')
			->with(1);

		$response = $this->controller->deleteScoreBook(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteScoreBookThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreBookService->expects($this->once())
			->method('deleteScoreBook')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot delete score book'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot delete score book');

		$this->controller->deleteScoreBook(1);
	}

	public function testDeleteScoreBookThrowsOCSBadRequestWhenBookHasLinkedScores(): void {
		$this->scoreBookService->expects($this->once())
			->method('deleteScoreBook')
			->with(1)
			->willThrowException(new \InvalidArgumentException('Cannot delete: book has linked scores'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Cannot delete: book has linked scores');

		$this->controller->deleteScoreBook(1);
	}

	public function testGetScoreBookScoresReturnsScores(): void {
		$expectedScores = [
			['id' => 1, 'title' => 'Score 1', 'index' => 1],
			['id' => 2, 'title' => 'Score 2', 'index' => 2],
		];

		$this->scoreBookService->expects($this->once())
			->method('getScoresInScoreBook')
			->with(1)
			->willReturn($expectedScores);

		$response = $this->controller->getScoreBookScores(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScores, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoreBookFolderCollectionsReturnsFolderCollections(): void {
		$expectedCollections = [
			['id' => 1, 'title' => 'Collection 1'],
		];

		$this->folderCollectionService->expects($this->once())
			->method('getFolderCollectionsForScoreBook')
			->with(1)
			->willReturn($expectedCollections);

		$response = $this->controller->getScoreBookFolderCollections(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedCollections, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testPostScoreBookScoreAddsScoreToBook(): void {
		$this->scoreBookService->expects($this->once())
			->method('addScoreToScoreBook')
			->with(1, 5, 10);

		$response = $this->controller->postScoreBookScore(id: 1, scoreId: 5, index: 10);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreBookScoreThrowsOCSBadRequestWhenScoreAlreadyInBook(): void {
		$this->scoreBookService->expects($this->once())
			->method('addScoreToScoreBook')
			->willThrowException(new \InvalidArgumentException('Score already in a book'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Score already in a book');

		$this->controller->postScoreBookScore(id: 1, scoreId: 5, index: 10);
	}

	public function testPostScoreBookScoresBatchAddsMultipleScores(): void {
		$scores = [
			['scoreId' => 1, 'index' => 1],
			['scoreId' => 2, 'index' => 2],
		];

		$this->scoreBookService->expects($this->once())
			->method('addScoresToScoreBook')
			->with(1, $scores);

		$response = $this->controller->postScoreBookScoresBatch(id: 1, scores: $scores);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreBookScoresBatchThrowsOCSBadRequestOnDuplicateIndex(): void {
		$scores = [
			['scoreId' => 1, 'index' => 1],
			['scoreId' => 2, 'index' => 1],
		];

		$this->scoreBookService->expects($this->once())
			->method('addScoresToScoreBook')
			->willThrowException(new \InvalidArgumentException('Duplicate index positions'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Duplicate index positions');

		$this->controller->postScoreBookScoresBatch(id: 1, scores: $scores);
	}

	public function testDeleteScoreBookScoreRemovesScoreFromBook(): void {
		$this->scoreBookService->expects($this->once())
			->method('removeScoreFromScoreBook')
			->with(1, 5);

		$response = $this->controller->deleteScoreBookScore(id: 1, scoreId: 5);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteScoreBookScoreThrowsOCSBadRequestWhenScoreNotInBook(): void {
		$this->scoreBookService->expects($this->once())
			->method('removeScoreFromScoreBook')
			->willThrowException(new \InvalidArgumentException('Score not in this book'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Score not in this book');

		$this->controller->deleteScoreBookScore(id: 1, scoreId: 5);
	}
}
