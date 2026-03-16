<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\SetlistService;
use OCA\OrchestraScoresManager\Utility\SetlistValidationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistService.
 */
final class SetlistServiceTest extends TestCase {
	private SetlistMapper $setlistMapper;
	private SetlistEntryMapper $setlistEntryMapper;
	private SetlistValidationHelper $validationHelper;
	private AuthorizationService $authorizationService;
	private SetlistPolicy $setlistPolicy;
	private IL10N $l10n;
	private SetlistService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->setlistMapper = $this->createMock(SetlistMapper::class);
		$this->setlistEntryMapper = $this->createMock(SetlistEntryMapper::class);
		$this->validationHelper = $this->createMock(SetlistValidationHelper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->setlistPolicy = $this->createMock(SetlistPolicy::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new SetlistService(
			$this->setlistMapper,
			$this->setlistEntryMapper,
			$this->validationHelper,
			$this->authorizationService,
			$this->setlistPolicy,
			$this->l10n
		);
	}

	/**
	 * Test getting setlists with different filter parameters
	 *
	 * @param string $filter The filter parameter
	 * @param string $mapperMethod The mapper method that should be called
	 * @param list<Setlist> $setlists The setlists to return from mapper
	 * @param bool|null $isDraft The isDraft filter
	 * @param bool|null $isPublished The isPublished filter
	 */
	#[DataProvider('filterProvider')]
	public function testGetSetlistsWithFilter(
		string $filter,
		string $mapperMethod,
		array $setlists,
		?bool $isDraft = null,
		?bool $isPublished = null,
	): void {
		$this->setlistMapper->expects($this->once())
			->method($mapperMethod)
			->with($isDraft, $isPublished)
			->willReturn($setlists);

		$this->setlistPolicy->expects($this->exactly(count($setlists)))
			->method('allows')
			->with(PolicyInterface::ACTION_READ, $this->anything())
			->willReturn(true);

		$result = $this->service->getSetlists($filter, $isDraft, $isPublished);

		$this->assertIsArray($result);
		$this->assertCount(count($setlists), $result);
	}

	public static function filterProvider(): array {
		$setlist1 = new Setlist();
		$setlist1->setId(1);
		$setlist1->setTitle('Test Setlist 1');

		$setlist2 = new Setlist();
		$setlist2->setId(2);
		$setlist2->setTitle('Test Setlist 2');

		return [
			'all filter' => ['all', 'findAll', [$setlist1, $setlist2], null, null],
			'future filter' => ['future', 'findFuture', [$setlist1], null, null],
			'past filter' => ['past', 'findPast', [$setlist2], null, null],
			'all with isDraft=true' => ['all', 'findAll', [$setlist1], true, null],
			'all with isPublished=true' => ['all', 'findAll', [$setlist2], null, true],
			'future with isDraft=false' => ['future', 'findFuture', [$setlist1], false, null],
			'past with both filters' => ['past', 'findPast', [$setlist2], false, true],
		];
	}

	public function testGetSetlistsWithInvalidFilter(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->getSetlists('invalid');
	}

	public function testGetSetlistByIdRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setTitle('Test Setlist');

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);

		$result = $this->service->getSetlistById(1);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testCreateSetlistRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setTitle('New Setlist');

		$createdSetlist = new Setlist();
		$createdSetlist->setId(1);
		$createdSetlist->setTitle('New Setlist');

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_CREATE);

		$this->setlistMapper->expects($this->once())
			->method('insert')
			->with($setlist)
			->willReturn($createdSetlist);

		$result = $this->service->createSetlist($setlist);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testUpdateSetlistRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setTitle('Updated Setlist');

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->setlistMapper->expects($this->once())
			->method('update')
			->with($setlist)
			->willReturn($setlist);

		$result = $this->service->updateSetlist($setlist);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testDeleteSetlistRequiresAuthorization(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_DELETE, $setlist);

		$this->setlistMapper->expects($this->once())
			->method('delete')
			->with($setlist);

		$this->service->deleteSetlist(1);
	}

	public function testGetSetlistEntries(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$entry1 = new SetlistEntry();
		$entry1->setId(1);
		$entry1->setSetlistId(1);
		$entry1->setIndex(0);

		$entry2 = new SetlistEntry();
		$entry2->setId(2);
		$entry2->setSetlistId(1);
		$entry2->setIndex(1);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);

		$this->setlistEntryMapper->expects($this->once())
			->method('findBySetlistId')
			->with(1)
			->willReturn([$entry1, $entry2]);

		$result = $this->service->getSetlistEntries(1);

		$this->assertIsArray($result);
		$this->assertCount(2, $result);
	}

	public function testCreateSetlistEntry(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$entry = new SetlistEntry();
		$entry->setIndex(0);
		$entry->setScoreId(5);

		$createdEntry = new SetlistEntry();
		$createdEntry->setId(1);
		$createdEntry->setSetlistId(1);
		$createdEntry->setIndex(0);
		$createdEntry->setScoreId(5);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->setlistEntryMapper->expects($this->once())
			->method('insert')
			->willReturn($createdEntry);

		$result = $this->service->createSetlistEntry(1, $entry);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
		$this->assertSame(1, $result['setlistId']);
	}

	public function testCreateSetlistEntryValidatesScoreInFolderCollectionVersion(): void {
		$setlist = new Setlist();
		$setlist->setId(1);
		$setlist->setFolderCollectionVersionId(10);

		$entry = new SetlistEntry();
		$entry->setIndex(0);
		$entry->setScoreId(5);

		$createdEntry = new SetlistEntry();
		$createdEntry->setId(1);
		$createdEntry->setSetlistId(1);
		$createdEntry->setIndex(0);
		$createdEntry->setScoreId(5);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		$this->validationHelper->expects($this->once())
			->method('validateScoreInFolderCollectionVersion')
			->with(5, 10);

		$this->setlistEntryMapper->expects($this->once())
			->method('insert')
			->willReturn($createdEntry);

		$result = $this->service->createSetlistEntry(1, $entry);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testFindSetlistEntityThrowsOnNotFound(): void {
		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->service->findSetlistEntity(999);
	}

	public function testCloneSetlist(): void {
		$source = new Setlist();
		$source->setId(1);
		$source->setTitle('Original Title');
		$source->setDescription('Some description');
		$source->setStartDateTime(new \DateTimeImmutable('2025-06-15T19:00:00Z'));
		$source->setDuration(3600);
		$source->setDefaultModerationDuration(120);
		$source->setFolderCollectionVersionId(10);
		$source->setIsDraft(false);
		$source->setIsPublished(true);

		$created = new Setlist();
		$created->setId(2);
		$created->setTitle('Clone Title');
		$created->setDescription('Some description');
		$created->setStartDateTime(new \DateTimeImmutable('2025-06-15T19:00:00Z'));
		$created->setDuration(3600);
		$created->setDefaultModerationDuration(120);
		$created->setFolderCollectionVersionId(10);
		$created->setIsDraft(true);
		$created->setIsPublished(false);

		$sourceEntry1 = new SetlistEntry();
		$sourceEntry1->setId(10);
		$sourceEntry1->setSetlistId(1);
		$sourceEntry1->setIndex(0);
		$sourceEntry1->setScoreId(5);
		$sourceEntry1->setComment('My comment');
		$sourceEntry1->setModerationDuration(60);

		$sourceEntry2 = new SetlistEntry();
		$sourceEntry2->setId(11);
		$sourceEntry2->setSetlistId(1);
		$sourceEntry2->setIndex(1);
		$sourceEntry2->setBreakDuration(300);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($source);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_CREATE);

		$this->setlistMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (Setlist $setlist) {
				$expectedDt = new \DateTimeImmutable('2025-06-15T19:00:00Z');
				return $setlist->getTitle() === 'Clone Title'
					&& $setlist->getDescription() === 'Some description'
					&& $setlist->getStartDateTime() == $expectedDt
					&& $setlist->getDuration() === 3600
					&& $setlist->getDefaultModerationDuration() === 120
					&& $setlist->getFolderCollectionVersionId() === 10
					&& $setlist->getIsDraft() === true
					&& $setlist->getIsPublished() === false;
			}))
			->willReturn($created);

		$this->setlistEntryMapper->expects($this->once())
			->method('findBySetlistId')
			->with(1)
			->willReturn([$sourceEntry1, $sourceEntry2]);

		$this->setlistEntryMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (SetlistEntry $entry) {
				$copy = new SetlistEntry();
				$copy->setId(100 + $entry->getIndex());
				$copy->setSetlistId($entry->getSetlistId());
				$copy->setIndex($entry->getIndex());
				$copy->setScoreId($entry->getScoreId());
				$copy->setComment($entry->getComment());
				$copy->setModerationDuration($entry->getModerationDuration());
				$copy->setBreakDuration($entry->getBreakDuration());
				return $copy;
			});

		$result = $this->service->cloneSetlist(1, 'Clone Title');

		$this->assertIsArray($result);
		$this->assertSame(2, $result['id']);
		$this->assertSame('Clone Title', $result['title']);
		$this->assertTrue($result['isDraft']);
		$this->assertFalse($result['isPublished']);
	}

	public function testCloneSetlistCopiesEntriesToNewSetlist(): void {
		$source = new Setlist();
		$source->setId(1);
		$source->setTitle('Original');

		$created = new Setlist();
		$created->setId(2);
		$created->setTitle('Clone');
		$created->setIsDraft(true);
		$created->setIsPublished(false);

		$scoreEntry = new SetlistEntry();
		$scoreEntry->setId(10);
		$scoreEntry->setSetlistId(1);
		$scoreEntry->setIndex(0);
		$scoreEntry->setScoreId(5);
		$scoreEntry->setComment('c1');
		$scoreEntry->setModerationDuration(60);

		$breakEntry = new SetlistEntry();
		$breakEntry->setId(11);
		$breakEntry->setSetlistId(1);
		$breakEntry->setIndex(1);
		$breakEntry->setBreakDuration(300);

		$this->setlistMapper->expects($this->once())->method('find')->with(1)->willReturn($source);
		$this->authorizationService->expects($this->once())->method('authorizePolicy');
		$this->setlistMapper->expects($this->once())->method('insert')->willReturn($created);

		$this->setlistEntryMapper->expects($this->once())
			->method('findBySetlistId')
			->with(1)
			->willReturn([$scoreEntry, $breakEntry]);

		$insertedEntries = [];
		$this->setlistEntryMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (SetlistEntry $entry) use (&$insertedEntries, $created) {
				$this->assertSame($created->getId(), $entry->getSetlistId());
				$insertedEntries[] = $entry;
				return $entry;
			});

		$this->service->cloneSetlist(1, 'Clone');

		$this->assertCount(2, $insertedEntries);

		// Verify score entry fields
		$this->assertSame(0, $insertedEntries[0]->getIndex());
		$this->assertSame(5, $insertedEntries[0]->getScoreId());
		$this->assertSame('c1', $insertedEntries[0]->getComment());
		$this->assertSame(60, $insertedEntries[0]->getModerationDuration());
		$this->assertNull($insertedEntries[0]->getBreakDuration());

		// Verify break entry fields
		$this->assertSame(1, $insertedEntries[1]->getIndex());
		$this->assertSame(300, $insertedEntries[1]->getBreakDuration());
		$this->assertNull($insertedEntries[1]->getScoreId());
	}

	public function testCloneSetlistThrowsWhenSourceNotFound(): void {
		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->service->cloneSetlist(999, 'New Title');
	}

	public function testCreateSetlistEntryThrowsOnDuplicateIndex(): void {
		$setlist = new Setlist();
		$setlist->setId(1);

		$entry = new SetlistEntry();
		$entry->setIndex(0);
		$entry->setScoreId(5);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($setlist);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		// Simulate a unique constraint violation
		$dbException = $this->createMock(\OCP\DB\Exception::class);
		$dbException->method('getReason')
			->willReturn(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION);

		$this->setlistEntryMapper->expects($this->once())
			->method('insert')
			->willThrowException($dbException);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('An entry with this index already exists in the setlist');
		$this->service->createSetlistEntry(1, $entry);
	}

	public function testUpdateSetlistValidatesScoresWhenVersionChanges(): void {
		// Existing setlist with version 10
		$existingSetlist = new Setlist();
		$existingSetlist->setId(1);
		$existingSetlist->setTitle('Test Setlist');
		$existingSetlist->setFolderCollectionVersionId(10);

		// Updated setlist with version 20
		$updatedSetlist = new Setlist();
		$updatedSetlist->setId(1);
		$updatedSetlist->setTitle('Test Setlist');
		$updatedSetlist->setFolderCollectionVersionId(20);

		// Existing entries with scores
		$entry1 = new SetlistEntry();
		$entry1->setId(1);
		$entry1->setSetlistId(1);
		$entry1->setIndex(0);
		$entry1->setScoreId(5);

		$entry2 = new SetlistEntry();
		$entry2->setId(2);
		$entry2->setSetlistId(1);
		$entry2->setIndex(1);
		$entry2->setBreakDuration(300);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $updatedSetlist);

		// Find to get existing setlist for version comparison
		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($existingSetlist);

		// Get entries to validate
		$this->setlistEntryMapper->expects($this->once())
			->method('findBySetlistId')
			->with(1)
			->willReturn([$entry1, $entry2]);

		// Validation should be called only for entry with scoreId
		$this->validationHelper->expects($this->once())
			->method('validateScoreInFolderCollectionVersion')
			->with(5, 20);

		$this->setlistMapper->expects($this->once())
			->method('update')
			->with($updatedSetlist)
			->willReturn($updatedSetlist);

		$result = $this->service->updateSetlist($updatedSetlist);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}

	public function testUpdateSetlistSkipsValidationWhenVersionUnchanged(): void {
		$existingSetlist = new Setlist();
		$existingSetlist->setId(1);
		$existingSetlist->setTitle('Test Setlist');
		$existingSetlist->setFolderCollectionVersionId(10);

		$updatedSetlist = new Setlist();
		$updatedSetlist->setId(1);
		$updatedSetlist->setTitle('Updated Title');
		$updatedSetlist->setFolderCollectionVersionId(10); // Same version

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $updatedSetlist);

		$this->setlistMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($existingSetlist);

		// Should not fetch entries or validate
		$this->setlistEntryMapper->expects($this->never())
			->method('findBySetlistId');

		$this->validationHelper->expects($this->never())
			->method('validateScoreInFolderCollectionVersion');

		$this->setlistMapper->expects($this->once())
			->method('update')
			->with($updatedSetlist)
			->willReturn($updatedSetlist);

		$result = $this->service->updateSetlist($updatedSetlist);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['id']);
	}
}
