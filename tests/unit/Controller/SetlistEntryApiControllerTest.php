<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\SetlistEntryApiController;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Service\SetlistEntryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistEntryApiController.
 */
final class SetlistEntryApiControllerTest extends TestCase {
	private SetlistEntryService $setlistEntryService;
	private IRequest $request;
	private SetlistEntryApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->setlistEntryService = $this->createMock(SetlistEntryService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new SetlistEntryApiController(
			'orchestrascoresmanager',
			$this->request,
			$this->setlistEntryService
		);
	}

	public function testGetSetlistEntryReturnsEntry(): void {
		$expectedEntry = ['id' => 1, 'index' => 0, 'scoreId' => 5];

		$this->setlistEntryService->expects($this->once())
			->method('getSetlistEntryById')
			->with(1)
			->willReturn($expectedEntry);

		$response = $this->controller->getSetlistEntry(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame($expectedEntry, $response->getData());
	}

	public function testPatchSetlistEntryUpdatesEntry(): void {
		$entry = new SetlistEntry();
		$entry->setId(1);
		$entry->setSetlistId(1);
		$entry->setIndex(0);
		$entry->setScoreId(5);
		$entry->setComment('Old comment');

		$expectedEntry = [
			'id' => 1,
			'setlistId' => 1,
			'index' => 0,
			'scoreId' => 5,
			'comment' => 'Updated comment',
			'moderationDuration' => null,
			'breakDuration' => null,
		];

		$this->setlistEntryService->expects($this->once())
			->method('findSetlistEntryEntity')
			->with(1)
			->willReturn($entry);

		$this->request->method('getParams')->willReturn([
			'id' => 1,
			'comment' => 'Updated comment',
		]);

		$this->setlistEntryService->expects($this->once())
			->method('updateSetlistEntry')
			->willReturn($expectedEntry);

		$response = $this->controller->patchSetlistEntry(1, null, 'Updated comment');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedEntry, $response->getData());
		// Verify that unchanged fields are present in response
		$this->assertArrayHasKey('scoreId', $response->getData());
		$this->assertSame(5, $response->getData()['scoreId']);
	}

	public function testDeleteSetlistEntryDeletesEntry(): void {
		$this->setlistEntryService->expects($this->once())
			->method('deleteSetlistEntry')
			->with(1);

		$response = $this->controller->deleteSetlistEntry(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testPostSetlistEntriesBatchUpdatesEntries(): void {
		$entries = [
			['id' => 1, 'index' => 0],
			['id' => 2, 'index' => 1],
		];

		$expectedEntries = [
			['id' => 1, 'index' => 0, 'scoreId' => 5],
			['id' => 2, 'index' => 1, 'scoreId' => 6],
		];

		$this->setlistEntryService->expects($this->once())
			->method('batchUpdateSetlistEntries')
			->with($entries)
			->willReturn($expectedEntries);

		$response = $this->controller->postSetlistEntriesBatch($entries);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedEntries, $response->getData());
	}
}
