<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\CommentApiController;
use OCA\OrchestraScoresManager\Db\Comment;
use OCA\OrchestraScoresManager\Service\CommentService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CommentApiController.
 */
final class CommentApiControllerTest extends TestCase {
	private CommentService $commentService;
	private IRequest $request;
	private CommentApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->commentService = $this->createMock(CommentService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new CommentApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->commentService
		);
	}

	public function testGetCommentReturnsComment(): void {
		$comment = new Comment();
		$comment->setId(1);
		$comment->setContent('Test comment');
		$comment->setUserId('user1');
		$comment->setScoreId(5);

		$this->commentService->expects($this->once())
			->method('getCommentById')
			->with(1)
			->willReturn($comment);

		$response = $this->controller->getComment(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($comment, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Not authorized'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Comment not found'])]
	public function testGetCommentThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->commentService->expects($this->once())
			->method('getCommentById')
			->with(1)
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->getComment(1);
	}

	public function testDeleteCommentReturnsEmptyResponse(): void {
		$this->commentService->expects($this->once())
			->method('deleteComment')
			->with(1);

		$response = $this->controller->deleteComment(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Cannot delete comment'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Comment does not exist'])]
	public function testDeleteCommentThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->commentService->expects($this->once())
			->method('deleteComment')
			->with(1)
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->deleteComment(1);
	}
}
