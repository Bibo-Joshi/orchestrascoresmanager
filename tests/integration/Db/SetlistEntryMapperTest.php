<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCP\DB\Exception;

/**
 * Integration tests for SetlistEntryMapper.
 *
 * These tests require a Nextcloud environment and run in CI.
 */
final class SetlistEntryMapperTest extends MapperTestCase {
	private SetlistEntryMapper $mapper;
	private SetlistMapper $setlistMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new SetlistEntryMapper($this->db);
		$this->setlistMapper = new SetlistMapper($this->db);
	}

	private function createTestSetlist(string $title = 'Test Setlist'): Setlist {
		$setlist = new Setlist();
		$setlist->setTitle($title);
		return $this->setlistMapper->insert($setlist);
	}

	private function createTestEntry(int $setlistId, int $index, ?int $scoreId = null): SetlistEntry {
		$entry = new SetlistEntry();
		$entry->setSetlistId($setlistId);
		$entry->setIndex($index);
		$entry->setScoreId($scoreId);
		return $this->mapper->insert($entry);
	}

	public function testFindBySetlistId(): void {
		$setlist = $this->createTestSetlist();
		$entry1 = $this->createTestEntry($setlist->getId(), 0);
		$entry2 = $this->createTestEntry($setlist->getId(), 1);
		$entry3 = $this->createTestEntry($setlist->getId(), 2);

		$entries = $this->mapper->findBySetlistId($setlist->getId());

		$this->assertCount(3, $entries);
		$this->assertSame(0, $entries[0]->getIndex());
		$this->assertSame(1, $entries[1]->getIndex());
		$this->assertSame(2, $entries[2]->getIndex());
	}

	public function testUniqueIndexConstraint(): void {
		$setlist = $this->createTestSetlist();
		$this->createTestEntry($setlist->getId(), 0);

		// Try to create another entry with the same index
		$duplicate = new SetlistEntry();
		$duplicate->setSetlistId($setlist->getId());
		$duplicate->setIndex(0);

		$this->expectException(Exception::class);
		$this->mapper->insert($duplicate);
	}

	public function testBatchUpdateSwapsEntriesWithinTransaction(): void {
		$setlist = $this->createTestSetlist();
		$entry1 = $this->createTestEntry($setlist->getId(), 1);
		$entry2 = $this->createTestEntry($setlist->getId(), 2);

		// Swap the entries: 1→2, 2→1
		$entry1->setIndex(2);
		$entry2->setIndex(1);

		$updated = $this->mapper->batchUpdate([$entry1, $entry2]);

		$this->assertCount(2, $updated);
		// Verify the swap happened
		$entries = $this->mapper->findBySetlistId($setlist->getId());
		$this->assertCount(2, $entries);
		$this->assertSame(1, $entries[0]->getIndex());
		$this->assertSame(2, $entries[1]->getIndex());
		// Verify IDs match swapped entries
		$this->assertSame($entry2->getId(), $entries[0]->getId());
		$this->assertSame($entry1->getId(), $entries[1]->getId());
	}

	public function testCascadeDeleteOnSetlistDelete(): void {
		$setlist = $this->createTestSetlist();
		$entry1 = $this->createTestEntry($setlist->getId(), 0);
		$entry2 = $this->createTestEntry($setlist->getId(), 1);

		// Delete the setlist
		$this->setlistMapper->delete($setlist);

		// Entries should be auto-deleted via CASCADE
		$entries = $this->mapper->findBySetlistId($setlist->getId());
		$this->assertCount(0, $entries);
	}

	public function testEntriesAreOrderedByIndex(): void {
		$setlist = $this->createTestSetlist();

		// Insert in random order
		$this->createTestEntry($setlist->getId(), 5);
		$this->createTestEntry($setlist->getId(), 1);
		$this->createTestEntry($setlist->getId(), 3);

		$entries = $this->mapper->findBySetlistId($setlist->getId());

		$this->assertCount(3, $entries);
		$this->assertSame(1, $entries[0]->getIndex());
		$this->assertSame(3, $entries[1]->getIndex());
		$this->assertSame(5, $entries[2]->getIndex());
	}
}
