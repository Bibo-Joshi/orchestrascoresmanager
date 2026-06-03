<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Integration tests for SetlistMapper.
 *
 * These tests require a Nextcloud environment and run in CI.
 */
final class SetlistMapperTest extends MapperTestCase {
	private SetlistMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new SetlistMapper($this->db);
	}

	private function createTestSetlist(string $title, ?\DateTimeImmutable $startDateTime = null, ?int $durationSeconds = null): Setlist {
		$setlist = new Setlist();
		$setlist->setTitle($title);
		if ($startDateTime !== null) {
			$setlist->setStartDateTime($startDateTime);
		}
		if ($durationSeconds !== null) {
			$setlist->setDuration($durationSeconds);
		}
		return $this->mapper->insert($setlist);
	}

	public function testFindAll(): void {
		$setlist1 = $this->createTestSetlist('Setlist 1', new \DateTimeImmutable('2024-01-01'));
		$setlist2 = $this->createTestSetlist('Setlist 2', new \DateTimeImmutable('2024-02-01'));
		$setlist3 = $this->createTestSetlist('Setlist 3', new \DateTimeImmutable('2024-03-01'));

		$all = $this->mapper->findAll();

		$this->assertGreaterThanOrEqual(3, count($all));
		$ids = array_map(fn ($s) => $s->getId(), $all);
		$this->assertContains($setlist1->getId(), $ids);
		$this->assertContains($setlist2->getId(), $ids);
		$this->assertContains($setlist3->getId(), $ids);
	}


	public function testFindFuture(): void {
		$past = $this->createTestSetlist('Past Setlist', new \DateTimeImmutable('-1 day'));
		$future1 = $this->createTestSetlist('Future Setlist 1', new \DateTimeImmutable('+1 day'));
		$future2 = $this->createTestSetlist('Future Setlist 2', new \DateTimeImmutable('+2 days'));

		$futureSetlists = $this->mapper->findFuture();

		$ids = array_map(fn ($s) => $s->getId(), $futureSetlists);
		$this->assertContains($future1->getId(), $ids);
		$this->assertContains($future2->getId(), $ids);
		$this->assertNotContains($past->getId(), $ids);
	}

	public function testFindFutureIncludesCurrentlyPlayingSetlist(): void {
		// Started 30 minutes ago, lasts 2 hours → still ongoing, must appear in future
		$playing = $this->createTestSetlist('Playing Setlist', new \DateTimeImmutable('-30 minutes'), 7200);
		// Started 2 hours ago, lasted 1 hour → already finished
		$finished = $this->createTestSetlist('Finished Setlist', new \DateTimeImmutable('-2 hours'), 3600);

		$ids = array_map(fn ($s) => $s->getId(), $this->mapper->findFuture());
		$this->assertContains($playing->getId(), $ids, 'A currently-playing setlist must be treated as future.');
		$this->assertNotContains($finished->getId(), $ids);
	}

	public function testFindPast(): void {
		$past1 = $this->createTestSetlist('Past Setlist 1', new \DateTimeImmutable('-2 days'));
		$past2 = $this->createTestSetlist('Past Setlist 2', new \DateTimeImmutable('-1 day'));
		$future = $this->createTestSetlist('Future Setlist', new \DateTimeImmutable('+1 day'));

		$pastSetlists = $this->mapper->findPast();

		$ids = array_map(fn ($s) => $s->getId(), $pastSetlists);
		$this->assertContains($past1->getId(), $ids);
		$this->assertContains($past2->getId(), $ids);
		$this->assertNotContains($future->getId(), $ids);
	}

	public function testFindPastExcludesCurrentlyPlayingSetlist(): void {
		// Started 30 minutes ago, lasts 2 hours → still ongoing, must not appear in past
		$playing = $this->createTestSetlist('Playing Setlist', new \DateTimeImmutable('-30 minutes'), 7200);
		// Started 2 hours ago, lasted 1 hour → already finished
		$finished = $this->createTestSetlist('Finished Setlist', new \DateTimeImmutable('-2 hours'), 3600);

		$ids = array_map(fn ($s) => $s->getId(), $this->mapper->findPast());
		$this->assertNotContains($playing->getId(), $ids, 'A currently-playing setlist must not be treated as past.');
		$this->assertContains($finished->getId(), $ids);
	}

	public function testFindNonExistentThrowsException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}
}
