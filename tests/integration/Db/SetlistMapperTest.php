<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Integration tests for SetlistMapper.
 *
 * These tests require a Nextcloud environment and run in CI.
 */
final class SetlistMapperTest extends MapperTestCase {
	private SetlistMapper $mapper;
	private SetlistEntryMapper $entryMapper;
	private ScoreMapper $scoreMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new SetlistMapper($this->db);
		$this->entryMapper = new SetlistEntryMapper($this->db);
		$this->scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}

	/**
	 * @param list<int> $scoreIds Scores to add as entries, in order
	 */
	private function createSetlistWithScores(string $title, ?\DateTimeImmutable $startDateTime, array $scoreIds, bool $published = true): Setlist {
		$setlist = new Setlist();
		$setlist->setTitle($title);
		if ($startDateTime !== null) {
			$setlist->setStartDateTime($startDateTime);
		}
		$setlist->setIsPublished($published);
		$setlist = $this->mapper->insert($setlist);

		foreach ($scoreIds as $index => $scoreId) {
			$entry = new SetlistEntry();
			$entry->setSetlistId($setlist->getId());
			$entry->setIndex($index);
			$entry->setScoreId($scoreId);
			$this->entryMapper->insert($entry);
		}
		return $setlist;
	}

	/**
	 * @param Setlist[] $setlists
	 * @return list<int>
	 */
	private function ids(array $setlists): array {
		return array_values(array_map(fn (Setlist $s) => $s->getId(), $setlists));
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

	public function testFindByScoreIdOnlyReturnsSetlistsContainingScoreWithoutDuplicates(): void {
		$score = $this->createTestScore();
		$other = $this->createTestScore();
		$withScoreTwice = $this->createSetlistWithScores('Twice', new \DateTimeImmutable('2024-01-01'), [$score->getId(), $other->getId(), $score->getId()]);
		$withScore = $this->createSetlistWithScores('Once', new \DateTimeImmutable('2024-02-01'), [$score->getId()]);
		$this->createSetlistWithScores('Other', new \DateTimeImmutable('2024-03-01'), [$other->getId()]);

		$found = $this->mapper->findByScoreId($score->getId(), false, 'date', 'ASC', 10, 0);

		$this->assertSame([$withScoreTwice->getId(), $withScore->getId()], $this->ids($found));
		$this->assertSame(2, $this->mapper->countByScoreId($score->getId(), false));
	}

	public function testFindByScoreIdPublishedOnly(): void {
		$score = $this->createTestScore();
		$published = $this->createSetlistWithScores('Published', new \DateTimeImmutable('2024-01-01'), [$score->getId()], true);
		$draft = $this->createSetlistWithScores('Draft', new \DateTimeImmutable('2024-02-01'), [$score->getId()], false);

		$publishedOnly = $this->mapper->findByScoreId($score->getId(), true, 'date', 'ASC', 10, 0);
		$all = $this->mapper->findByScoreId($score->getId(), false, 'date', 'ASC', 10, 0);

		$this->assertSame([$published->getId()], $this->ids($publishedOnly));
		$this->assertSame([$published->getId(), $draft->getId()], $this->ids($all));
		$this->assertSame(1, $this->mapper->countByScoreId($score->getId(), true));
		$this->assertSame(2, $this->mapper->countByScoreId($score->getId(), false));
	}

	public function testFindByScoreIdSortByDateTreatsMissingDateAsNewest(): void {
		$score = $this->createTestScore();
		$yesterday = $this->createSetlistWithScores('Yesterday', new \DateTimeImmutable('-1 day'), [$score->getId()]);
		$today = $this->createSetlistWithScores('Today', new \DateTimeImmutable('today'), [$score->getId()]);
		$undated = $this->createSetlistWithScores('Undated', null, [$score->getId()]);

		$desc = $this->mapper->findByScoreId($score->getId(), false, 'date', 'DESC', 10, 0);
		$asc = $this->mapper->findByScoreId($score->getId(), false, 'date', 'ASC', 10, 0);

		$this->assertSame([$undated->getId(), $today->getId(), $yesterday->getId()], $this->ids($desc));
		$this->assertSame([$yesterday->getId(), $today->getId(), $undated->getId()], $this->ids($asc));
	}

	public function testFindByScoreIdSortByNameAndCreation(): void {
		$score = $this->createTestScore();
		$b = $this->createSetlistWithScores('B', new \DateTimeImmutable('2024-01-01'), [$score->getId()]);
		$c = $this->createSetlistWithScores('C', new \DateTimeImmutable('2024-02-01'), [$score->getId()]);
		$a = $this->createSetlistWithScores('A', new \DateTimeImmutable('2024-03-01'), [$score->getId()]);

		$this->assertSame(
			[$a->getId(), $b->getId(), $c->getId()],
			$this->ids($this->mapper->findByScoreId($score->getId(), false, 'name', 'ASC', 10, 0)),
		);
		$this->assertSame(
			[$a->getId(), $c->getId(), $b->getId()],
			$this->ids($this->mapper->findByScoreId($score->getId(), false, 'creation', 'DESC', 10, 0)),
		);
	}

	public function testFindByScoreIdLimitAndOffset(): void {
		$score = $this->createTestScore();
		$first = $this->createSetlistWithScores('1', new \DateTimeImmutable('2024-01-01'), [$score->getId()]);
		$second = $this->createSetlistWithScores('2', new \DateTimeImmutable('2024-02-01'), [$score->getId()]);
		$third = $this->createSetlistWithScores('3', new \DateTimeImmutable('2024-03-01'), [$score->getId()]);

		$page = $this->mapper->findByScoreId($score->getId(), false, 'date', 'ASC', 2, 1);

		$this->assertSame([$second->getId(), $third->getId()], $this->ids($page));
		$this->assertNotContains($first->getId(), $this->ids($page));
		$this->assertSame(3, $this->mapper->countByScoreId($score->getId(), false));
	}

	public function testCountByScoreIdWithoutSetlists(): void {
		$score = $this->createTestScore();

		$this->assertSame(0, $this->mapper->countByScoreId($score->getId(), false));
		$this->assertSame([], $this->mapper->findByScoreId($score->getId(), false, 'date', 'DESC', 10, 0));
	}
}
