<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\TagApiController;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\TagService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TagApiController.
 */
final class TagApiControllerTest extends TestCase {
	private TagMapper $tagMapper;
	private TagService $tagService;
	private IRequest $request;
	private TagApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->tagService = $this->createMock(TagService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new TagApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->tagMapper,
			$this->tagService
		);
	}

	public function testGetTagsReturnsAllTags(): void {
		$expectedTags = [
			['id' => 1, 'name' => 'Classical'],
			['id' => 2, 'name' => 'Modern'],
		];

		$this->tagService->expects($this->once())
			->method('getAllTags')
			->willReturn($expectedTags);

		$response = $this->controller->getTags();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($expectedTags, $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testGetTagsReturnsEmptyArray(): void {
		$this->tagService->expects($this->once())
			->method('getAllTags')
			->willReturn([]);

		$response = $this->controller->getTags();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
	}

	public function testGetTagsThrowsOCSForbiddenWhenNotAuthorized(): void {
		$this->tagService->expects($this->once())
			->method('getAllTags')
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Not authorized');

		$this->controller->getTags();
	}

	public function testPostTagCreatesNewTag(): void {
		$tagName = 'Jazz';
		$createdTag = new Tag();
		$createdTag->setId(3);
		$createdTag->setName('Jazz');

		$this->tagService->expects($this->once())
			->method('createTag')
			->with($tagName)
			->willReturn($createdTag);

		$response = $this->controller->postTag($tagName);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals($createdTag, $response->getData());
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Cannot create tag'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Tag name cannot be empty'])]
	public function testPostTagThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->tagService->expects($this->once())
			->method('createTag')
			->with('Test')
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->postTag('Test');
	}
}
