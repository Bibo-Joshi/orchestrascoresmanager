<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\ScoreApiController;
use OCA\OrchestraScoresManager\Db\Comment;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Service\CommentService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\ScoreService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreApiController.
 */
final class ScoreApiControllerTest extends TestCase {
	private ScoreMapper $scoreMapper;
	private ScoreTagLinkMapper $linkMapper;
	private TagMapper $tagMapper;
	private ScoreService $scoreService;
	private CommentService $commentService;
	private FolderCollectionService $folderCollectionService;
	private IRequest $request;
	private ScoreApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->linkMapper = $this->createMock(ScoreTagLinkMapper::class);
		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->scoreService = $this->createMock(ScoreService::class);
		$this->commentService = $this->createMock(CommentService::class);
		$this->folderCollectionService = $this->createMock(FolderCollectionService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new ScoreApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->scoreMapper,
			$this->linkMapper,
			$this->tagMapper,
			$this->scoreService,
			$this->commentService,
			$this->folderCollectionService
		);
	}

	public function testGetScoresReturnsAllScores(): void {
		$expectedScores = [
			['id' => 1, 'title' => 'Symphony No. 1'],
			['id' => 2, 'title' => 'Symphony No. 2'],
		];

		$this->scoreService->expects($this->once())
			->method('getAllScores')
			->willReturn($expectedScores);

		$response = $this->controller->getScores();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScores, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoresWithIdsReturnsSpecificScores(): void {
		$expectedScores = [
			['id' => 1, 'title' => 'Symphony No. 1'],
			['id' => 3, 'title' => 'Symphony No. 3'],
		];

		$this->scoreService->expects($this->once())
			->method('getScoresByIds')
			->with([1, 3])
			->willReturn($expectedScores);

		$response = $this->controller->getScores([1, 3]);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedScores, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoresThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreService->expects($this->once())
			->method('getAllScores')
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Not authorized');

		$this->controller->getScores();
	}

	public function testPostScoreCreatesNewScore(): void {
		$score = new Score();
		$score->setId(1);
		$score->setTitle('New Score');

		$this->scoreService->expects($this->once())
			->method('createScore')
			->willReturn($score);

		$response = $this->controller->postScore(
			title: 'New Score',
			composer: 'Mozart'
		);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($score, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreWithTagIdsAndScoreBook(): void {
		$score = new Score();
		$score->setId(1);
		$score->setTitle('New Score');

		$tagIds = [1, 2, 3];
		$scoreBook = ['id' => 5, 'index' => 10];

		$this->scoreService->expects($this->once())
			->method('createScore')
			->with(
				$this->isInstanceOf(Score::class),
				$tagIds,
				['scoreBookId' => 5, 'index' => 10]
			)
			->willReturn($score);

		$response = $this->controller->postScore(
			title: 'New Score',
			tagIds: $tagIds,
			scoreBook: $scoreBook
		);

		$this->assertEquals($score, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Cannot create score'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Title cannot be empty'])]
	public function testPostScoreThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->scoreService->expects($this->once())
			->method('createScore')
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->postScore(title: 'Test');
	}

	public function testPatchScoreUpdatesExistingScore(): void {
		$score = new Score();
		$score->setId(1);
		$score->setTitle('Original Title');
		$score->setComposer('Mozart');
		$score->setYear(1791);

		$updatedScore = new Score();
		$updatedScore->setId(1);
		$updatedScore->setTitle('Updated Title');
		$updatedScore->setComposer('Mozart');
		$updatedScore->setYear(1791);

		$this->scoreService->expects($this->once())
			->method('getScoreById')
			->with(1)
			->willReturn($score);

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn(['id' => 1, 'title' => 'Updated Title']);

		$this->scoreService->expects($this->once())
			->method('updateScore')
			->willReturn($updatedScore);

		$response = $this->controller->patchScore(id: 1, title: 'Updated Title');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($updatedScore, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		// Verify that unchanged fields are present in response
		$this->assertEquals('Mozart', $response->getData()->getComposer());
		$this->assertEquals(1791, $response->getData()->getYear());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Cannot update score'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Score not found'])]
	public function testPatchScoreThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->scoreService->expects($this->once())
			->method('getScoreById')
			->with(1)
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->patchScore(id: 1, title: 'New Title');
	}

	public function testDeleteScoreRemovesScore(): void {
		$this->scoreService->expects($this->once())
			->method('deleteScoreById')
			->with(1);

		$response = $this->controller->deleteScore(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertNull($response->getData());
		$this->assertEquals(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function testDeleteScoreThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->scoreService->expects($this->once())
			->method('deleteScoreById')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot delete score'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot delete score');

		$this->controller->deleteScore(1);
	}

	public function testGetScoreCommentsReturnsComments(): void {
		$expectedComments = [
			['id' => 1, 'content' => 'Great piece!', 'scoreId' => 1],
			['id' => 2, 'content' => 'Needs work', 'scoreId' => 1],
		];

		$this->commentService->expects($this->once())
			->method('getCommentsForScore')
			->with(1)
			->willReturn($expectedComments);

		$response = $this->controller->getScoreComments(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedComments, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoreCommentsThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->commentService->expects($this->once())
			->method('getCommentsForScore')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot view comments'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot view comments');

		$this->controller->getScoreComments(1);
	}

	public function testPostScoreCommentCreatesComment(): void {
		$comment = new Comment();
		$comment->setId(1);
		$comment->setContent('New comment');
		$comment->setUserId('user1');
		$comment->setScoreId(1);

		$this->commentService->expects($this->once())
			->method('createComment')
			->with($this->isInstanceOf(Comment::class))
			->willReturn($comment);

		$response = $this->controller->postScoreComment(
			id: 1,
			content: 'New comment',
			userId: 'user1',
			creationDate: 1234567890
		);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($comment, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreCommentThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->commentService->expects($this->once())
			->method('createComment')
			->willThrowException(new PermissionDeniedException('Cannot create comment'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot create comment');

		$this->controller->postScoreComment(
			id: 1,
			content: 'Test',
			userId: 'user1',
			creationDate: 1234567890
		);
	}

	public function testGetScoreFolderCollectionsReturnsFolderCollections(): void {
		$expectedCollections = [
			['id' => 1, 'title' => 'Concert Program 1'],
			['id' => 2, 'title' => 'Concert Program 2'],
		];

		$this->folderCollectionService->expects($this->once())
			->method('getFolderCollectionsForScore')
			->with(1)
			->willReturn($expectedCollections);

		$response = $this->controller->getScoreFolderCollections(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedCollections, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetScoreFolderCollectionsThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->folderCollectionService->expects($this->once())
			->method('getFolderCollectionsForScore')
			->with(1)
			->willThrowException(new PermissionDeniedException('Cannot view folder collections'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Cannot view folder collections');

		$this->controller->getScoreFolderCollections(1);
	}
}
