<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\TagPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\TagService;
use OCP\DB\Exception;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TagService.
 */
final class TagServiceTest extends TestCase {
	private TagMapper $tagMapper;
	private AuthorizationService $authorizationService;
	private TagPolicy $tagPolicy;
	private TagService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->tagPolicy = $this->createMock(TagPolicy::class);

		$this->service = new TagService(
			$this->tagMapper,
			$this->authorizationService,
			$this->tagPolicy
		);
	}

	public function testGetAllTagsRequiresAuthorization(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->tagPolicy, PolicyInterface::ACTION_READ);

		$expectedTags = [new Tag(), new Tag()];

		$this->tagMapper->expects($this->once())
			->method('findAll')
			->willReturn($expectedTags);

		$result = $this->service->getAllTags();

		$this->assertSame($expectedTags, $result);
	}

	public function testGetAllTagsThrowsWhenNotAuthorized(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->tagPolicy, PolicyInterface::ACTION_READ)
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->tagMapper->expects($this->never())
			->method('findAll');

		$this->expectException(PermissionDeniedException::class);

		$this->service->getAllTags();
	}

	public function testCreateTagRequiresAuthorization(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->tagPolicy, PolicyInterface::ACTION_CREATE);

		$this->tagMapper->expects($this->once())
			->method('findByName')
			->with('New Tag')
			->willReturn(null);

		$expectedTag = new Tag();
		$expectedTag->setName('New Tag');

		$this->tagMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function ($tag) {
				// Tag names are normalized to lowercase
				return $tag instanceof Tag && $tag->getName() === 'new tag';
			}))
			->willReturn($expectedTag);

		$result = $this->service->createTag('New Tag');

		$this->assertSame($expectedTag, $result);
	}

	public function testCreateTagReturnsExistingTagWhenAlreadyExists(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->tagPolicy, PolicyInterface::ACTION_CREATE);

		$existingTag = new Tag();
		$existingTag->setId(1);
		$existingTag->setName('Existing Tag');

		$this->tagMapper->expects($this->once())
			->method('findByName')
			->with('Existing Tag')
			->willReturn($existingTag);

		$this->tagMapper->expects($this->never())
			->method('insert');

		$result = $this->service->createTag('Existing Tag');

		$this->assertSame($existingTag, $result);
	}

	public function testCreateTagThrowsWhenNotAuthorized(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->tagPolicy, PolicyInterface::ACTION_CREATE)
			->willThrowException(new PermissionDeniedException('Not authorized'));

		$this->tagMapper->expects($this->never())
			->method('findByName');

		$this->tagMapper->expects($this->never())
			->method('insert');

		$this->expectException(PermissionDeniedException::class);

		$this->service->createTag('New Tag');
	}

	public function testCreateTagPropagatesMapperException(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->tagMapper->expects($this->once())
			->method('findByName')
			->willReturn(null);

		$this->tagMapper->expects($this->once())
			->method('insert')
			->willThrowException(new Exception('Database error'));

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Database error');

		$this->service->createTag('New Tag');
	}
}
