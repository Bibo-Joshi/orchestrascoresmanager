<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\SetlistEntryService;
use OCA\OrchestraScoresManager\Service\SetlistService;
use OCA\OrchestraScoresManager\Utility\SetlistValidationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistEntryService.
 */
final class SetlistEntryServiceTest extends TestCase {
	private SetlistEntryMapper $setlistEntryMapper;
	private SetlistService $setlistService;
	private SetlistValidationHelper $validationHelper;
	private AuthorizationService $authorizationService;
	private SetlistPolicy $setlistPolicy;
	private IL10N $l10n;
	private SetlistEntryService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->setlistEntryMapper = $this->createMock(SetlistEntryMapper::class);
		$this->setlistService = $this->createMock(SetlistService::class);
		$this->validationHelper = $this->createMock(SetlistValidationHelper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->setlistPolicy = $this->createMock(SetlistPolicy::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new SetlistEntryService(
			$this->setlistEntryMapper,
			$this->setlistService,
			$this->validationHelper,
			$this->authorizationService,
			$this->setlistPolicy,
			$this->l10n
		);
	}

	public function testGetSetlistEntryByIdRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$entry = new SetlistEntry();
		$entry->setId(1);
		$entry->setSetlistId(1);
		$entry->setIndex(0);

		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($entry);

		$this->setlistService->expects($this->once())
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);

		$result = $this->service->getSetlistEntryById(1);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testFindSetlistEntryEntityReturnsEntity(): void {
		$entry = new SetlistEntry();
		$entry->setId(1);
		$entry->setSetlistId(1);
		$entry->setIndex(0);

		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($entry);

		$result = $this->service->findSetlistEntryEntity(1);

		$this->assertInstanceOf(SetlistEntry::class, $result);
		$this->assertSame(1, $result->getId());
	}

	public function testFindSetlistEntryEntityThrowsOnNotFound(): void {
		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->service->findSetlistEntryEntity(999);
	}

	public function testUpdateSetlistEntryRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$existingEntry = new SetlistEntry();
		$existingEntry->setId(1);
		$existingEntry->setSetlistId(1);
		$existingEntry->setIndex(0);

		$updatedEntry = new SetlistEntry();
		$updatedEntry->setId(1);
		$updatedEntry->setSetlistId(1);
		$updatedEntry->setIndex(0);
		$updatedEntry->setComment('Updated comment');

		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($existingEntry);

		$this->setlistService->expects($this->once())
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->setlistEntryMapper->expects($this->once())
			->method('update')
			->willReturn($updatedEntry);

		$entry = new SetlistEntry();
		$entry->setComment('Updated comment');

		$result = $this->service->updateSetlistEntry(1, $entry);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testDeleteSetlistEntryRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$entry = new SetlistEntry();
		$entry->setId(1);
		$entry->setSetlistId(1);

		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($entry);

		$this->setlistService->expects($this->once())
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->setlistEntryMapper->expects($this->once())
			->method('delete')
			->with($entry);

		$this->service->deleteSetlistEntry(1);
	}

	public function testGetSetlistEntryByIdThrowsOnNotFound(): void {
		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->service->getSetlistEntryById(999);
	}

	public function testUpdateSetlistEntryValidatesScoreInFolderCollectionVersion(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setFolderCollectionVersionId(10);

		$existingEntry = new SetlistEntry();
		$existingEntry->setId(1);
		$existingEntry->setSetlistId(1);
		$existingEntry->setIndex(0);
		$existingEntry->setScoreId(3);

		$updatedEntry = new SetlistEntry();
		$updatedEntry->setId(1);
		$updatedEntry->setSetlistId(1);
		$updatedEntry->setIndex(0);
		$updatedEntry->setScoreId(5);

		$this->setlistEntryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($existingEntry);

		$this->setlistService->expects($this->once())
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->validationHelper->expects($this->once())
			->method('validateScoreInFolderCollectionVersion')
			->with(5, 10);

		$this->setlistEntryMapper->expects($this->once())
			->method('update')
			->willReturn($updatedEntry);

		$entry = new SetlistEntry();
		$entry->setScoreId(5);

		$result = $this->service->updateSetlistEntry(1, $entry);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testBatchUpdateSetlistEntriesValidatesScoreInFolderCollectionVersion(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setFolderCollectionVersionId(10);

		$entry1 = new SetlistEntry();
		$entry1->setId(1);
		$entry1->setSetlistId(1);
		$entry1->setIndex(0);
		$entry1->setScoreId(3);

		$entry2 = new SetlistEntry();
		$entry2->setId(2);
		$entry2->setSetlistId(1);
		$entry2->setIndex(1);
		$entry2->setScoreId(4);

		$this->setlistEntryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(fn ($id) => $id === 1 ? $entry1 : $entry2);

		$this->setlistService->expects($this->exactly(2))
			->method('findSetlistEntity')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		// Expect validation for both score changes
		$this->validationHelper->expects($this->exactly(2))
			->method('validateScoreInFolderCollectionVersion')
			->willReturnCallback(function ($scoreId, $versionId) {
				$this->assertSame(10, $versionId);
				$this->assertContains($scoreId, [5, 6]);
			});

		$this->setlistEntryMapper->expects($this->once())
			->method('batchUpdate')
			->willReturnCallback(fn ($entries) => $entries);

		$batchData = [
			['id' => 1, 'scoreId' => 5],
			['id' => 2, 'scoreId' => 6],
		];

		$result = $this->service->batchUpdateSetlistEntries($batchData);

		$this->assertIsArray($result);
		$this->assertCount(2, $result);
	}
}
