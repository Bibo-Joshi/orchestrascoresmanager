<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\SetlistApiController;
use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Service\SetlistService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistApiController.
 */
final class SetlistApiControllerTest extends TestCase {
	private SetlistService $setlistService;
	private IRequest $request;
	private SetlistApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->setlistService = $this->createMock(SetlistService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new SetlistApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->setlistService
		);
	}

	/**
	 * Test getting setlists with different filter parameters
	 *
	 * @param string $filter The filter parameter
	 * @param bool|null $isDraft The isDraft filter
	 * @param bool|null $isPublished The isPublished filter
	 */
	#[DataProvider('filterProvider')]
	public function testGetSetlistsWithFilter(string $filter, ?bool $isDraft = null, ?bool $isPublished = null): void {
		$expectedSetlists = [
			['id' => 1, 'title' => 'Setlist 1'],
		];

		$this->setlistService->expects($this->once())
			->method('getSetlists')
			->with($filter, $isDraft, $isPublished)
			->willReturn($expectedSetlists);

		$response = $this->controller->getSetlists($filter, $isDraft, $isPublished);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedSetlists, $response->getData());
	}

	public static function filterProvider(): array {
		return [
			'all' => ['all', null, null],
			'future' => ['future', null, null],
			'past' => ['past', null, null],
			'all drafts' => ['all', true, null],
			'all published' => ['all', null, true],
			'future drafts' => ['future', true, null],
			'past published' => ['past', null, true],
			'all non-drafts published' => ['all', false, true],
		];
	}

	public function testGetSetlistReturnsSpecificSetlist(): void {
		$expectedSetlist = ['id' => 1, 'title' => 'Test Setlist'];

		$this->setlistService->expects($this->once())
			->method('getSetlistById')
			->with(1)
			->willReturn($expectedSetlist);

		$response = $this->controller->getSetlist(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame($expectedSetlist, $response->getData());
	}

	public function testPostSetlistCreatesNewSetlist(): void {
		$expectedSetlist = [
			'id' => 1,
			'title' => 'New Setlist',
			'description' => 'Test description',
		];

		$this->setlistService->expects($this->once())
			->method('createSetlist')
			->willReturn($expectedSetlist);

		$response = $this->controller->postSetlist(
			'New Setlist',
			'Test description'
		);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($expectedSetlist, $response->getData());
	}

	public function testPatchSetlistUpdatesSetlist(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setTitle('Old Title');
		$setlist->setDescription('Original description');
		$setlist->setDuration(3600);

		$expectedSetlist = [
			'id' => 1,
			'title' => 'Updated Setlist',
			'description' => 'Original description',
			'duration' => 3600,
		];

		$this->request->method('getParams')->willReturn([
			'id' => 1,
			'title' => 'Updated Setlist',
		]);

		$this->setlistService->expects($this->once())
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->setlistService->expects($this->once())
			->method('updateSetlist')
			->willReturn($expectedSetlist);

		$response = $this->controller->patchSetlist(1, 'Updated Setlist');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedSetlist, $response->getData());
		// Verify that unchanged fields are present in response
		$this->assertArrayHasKey('description', $response->getData());
		$this->assertSame('Original description', $response->getData()['description']);
		$this->assertArrayHasKey('duration', $response->getData());
		$this->assertSame(3600, $response->getData()['duration']);
	}

	public function testDeleteSetlistDeletesSetlist(): void {
		$this->setlistService->expects($this->once())
			->method('deleteSetlist')
			->with(1);

		$response = $this->controller->deleteSetlist(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testGetSetlistEntriesReturnsEntries(): void {
		$expectedEntries = [
			['id' => 1, 'index' => 0, 'scoreId' => 5],
			['id' => 2, 'index' => 1, 'scoreId' => 6],
		];

		$this->setlistService->expects($this->once())
			->method('getSetlistEntries')
			->with(1)
			->willReturn($expectedEntries);

		$response = $this->controller->getSetlistEntries(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame($expectedEntries, $response->getData());
	}

	public function testPostSetlistEntryCreatesEntry(): void {
		$expectedEntry = [
			'id' => 1,
			'setlistId' => 1,
			'index' => 0,
			'scoreId' => 5,
		];

		$this->setlistService->expects($this->once())
			->method('createSetlistEntry')
			->willReturn($expectedEntry);

		$response = $this->controller->postSetlistEntry(1, 0, null, null, null, 5);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($expectedEntry, $response->getData());
	}

	public function testPostCloneSetlistClonesSetlist(): void {
		$expectedSetlist = [
			'id' => 2,
			'title' => 'Clone Title',
			'isDraft' => true,
			'isPublished' => false,
		];

		$this->setlistService->expects($this->once())
			->method('cloneSetlist')
			->with(1, 'Clone Title')
			->willReturn($expectedSetlist);

		$response = $this->controller->postCloneSetlist(1, 'Clone Title');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($expectedSetlist, $response->getData());
	}
}
