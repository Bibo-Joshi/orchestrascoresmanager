<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\DB\Exception;

/**
 * Integration tests for ScoreFolderCollectionLinkMapper.
 *
 * Tests the most complex link table with score/scorebook XOR logic.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreFolderCollectionLinkMapperTest extends MapperTestCase {
	private ScoreFolderCollectionLinkMapper $mapper;
	private ScoreMapper $scoreMapper;
	private ScoreBookMapper $scoreBookMapper;
	private FolderCollectionMapper $fcMapper;
	private FolderCollectionVersionMapper $fcVersionMapper;

	protected function setUp(): void {
		parent::setUp();

		$tagMapper = new TagMapper($this->db);
		$scoreTagLinkMapper = new ScoreTagLinkMapper($this->db);
		$scoreBookScoreLinkMapper = new ScoreBookScoreLinkMapper($this->db);
		$scoreBookTagLinkMapper = new ScoreBookTagLinkMapper($this->db);
		$this->mapper = new ScoreFolderCollectionLinkMapper($this->db);
		$this->scoreMapper = new ScoreMapper($this->db, $scoreTagLinkMapper, $tagMapper, $scoreBookScoreLinkMapper);
		$this->scoreBookMapper = new ScoreBookMapper($this->db, $scoreBookTagLinkMapper, $tagMapper);
		$this->fcMapper = new FolderCollectionMapper($this->db);
		$this->fcVersionMapper = new FolderCollectionVersionMapper($this->db);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}
	private function createTestScoreBook(): ScoreBook {
		$scoreBook = new ScoreBook();
		$scoreBook->setTitle('Test Book');
		return $this->scoreBookMapper->insert($scoreBook);
	}
	private function createTestFolderCollectionVersion(): FolderCollectionVersion {
		$fc = new FolderCollection();
		$fc->setTitle('Test Collection');
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		$insertedFc = $this->fcMapper->insert($fc);

		$version = new FolderCollectionVersion();
		$version->setFolderCollectionId($insertedFc->getId());
		$version->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$version->setValidTo(null);
		return $this->fcVersionMapper->insert($version);
	}

	public function testFindVersionsForScore(): void {
		$score = $this->createTestScore();
		$version1 = $this->createTestFolderCollectionVersion();
		$version2 = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreToVersion($score->getId(), $version1->getId(), 1);
		$this->mapper->addScoreToVersion($score->getId(), $version2->getId(), 2);

		$versions = $this->mapper->findVersionsForScore($score->getId());

		$this->assertCount(2, $versions);
		$versionIds = array_map(fn ($v) => $v['versionId'], $versions);
		$this->assertContains($version1->getId(), $versionIds);
		$this->assertContains($version2->getId(), $versionIds);
	}

	public function testFindVersionsForScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$version1 = $this->createTestFolderCollectionVersion();
		$version2 = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version1->getId(), 1);
		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version2->getId(), null);

		$versions = $this->mapper->findVersionsForScoreBook($scoreBook->getId());

		$this->assertCount(2, $versions);
		$this->assertSame($version1->getId(), $versions[0]['versionId']);
		$this->assertSame(1, $versions[0]['index']);
		$this->assertNull($versions[1]['index']);
	}

	public function testFindScoresForVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$this->mapper->addScoreToVersion($score1->getId(), $version->getId(), 1);
		$this->mapper->addScoreToVersion($score2->getId(), $version->getId(), 2);
		$this->mapper->addScoreToVersion($score3->getId(), $version->getId(), null);

		$scores = $this->mapper->findScoresForVersion($version->getId());

		$this->assertCount(3, $scores);
		$scoreIds = array_map(fn ($s) => $s['id'], $scores);
		$this->assertContains($score1->getId(), $scoreIds);
		$this->assertContains($score2->getId(), $scoreIds);
		$this->assertContains($score3->getId(), $scoreIds);
	}

	public function testFindScoreBooksForVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$sb1 = $this->createTestScoreBook();
		$sb2 = $this->createTestScoreBook();

		$this->mapper->addScoreBookToVersion($sb1->getId(), $version->getId(), 1);
		$this->mapper->addScoreBookToVersion($sb2->getId(), $version->getId(), 2);

		$scoreBooks = $this->mapper->findScoreBooksForVersion($version->getId());

		$this->assertCount(2, $scoreBooks);
		$this->assertSame($sb1->getId(), $scoreBooks[0]['id']);
		$this->assertSame(1, $scoreBooks[0]['index']);
		$this->assertSame($sb2->getId(), $scoreBooks[1]['id']);
		$this->assertSame(2, $scoreBooks[1]['index']);
	}

	public function testAddScoreToVersion(): void {
		$score = $this->createTestScore();
		$version = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 5);

		$scores = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(1, $scores);
		$this->assertSame($score->getId(), $scores[0]['id']);
		$this->assertSame(5, $scores[0]['index']);
	}

	public function testAddScoreToVersionWithNullIndex(): void {
		$score = $this->createTestScore();
		$version = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), null);

		$scores = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(1, $scores);
		$this->assertNull($scores[0]['index']);
	}

	public function testAddScoreBookToVersion(): void {
		$scoreBook = $this->createTestScoreBook();
		$version = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version->getId(), 3);

		$scoreBooks = $this->mapper->findScoreBooksForVersion($version->getId());
		$this->assertCount(1, $scoreBooks);
		$this->assertSame($scoreBook->getId(), $scoreBooks[0]['id']);
		$this->assertSame(3, $scoreBooks[0]['index']);
	}

	public function testRemoveScoreFromVersion(): void {
		$score = $this->createTestScore();
		$version = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 1);

		$scores = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(1, $scores);

		$this->mapper->removeScoreFromVersion($score->getId(), $version->getId());

		$scoresAfter = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(0, $scoresAfter);
	}

	public function testRemoveScoreBookFromVersion(): void {
		$scoreBook = $this->createTestScoreBook();
		$version = $this->createTestFolderCollectionVersion();

		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version->getId(), 1);

		$scoreBooks = $this->mapper->findScoreBooksForVersion($version->getId());
		$this->assertCount(1, $scoreBooks);

		$this->mapper->removeScoreBookFromVersion($scoreBook->getId(), $version->getId());

		$scoreBooksAfter = $this->mapper->findScoreBooksForVersion($version->getId());
		$this->assertCount(0, $scoreBooksAfter);
	}

	public function testCountScoresInVersion(): void {
		$version = $this->createTestFolderCollectionVersion();

		$this->assertSame(0, $this->mapper->countScoresInVersion($version->getId()));

		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();

		$this->mapper->addScoreToVersion($score1->getId(), $version->getId(), 1);
		$this->mapper->addScoreToVersion($score2->getId(), $version->getId(), 2);

		$this->assertSame(2, $this->mapper->countScoresInVersion($version->getId()));
	}

	public function testCountScoreBooksInVersion(): void {
		$version = $this->createTestFolderCollectionVersion();

		$this->assertSame(0, $this->mapper->countScoreBooksInVersion($version->getId()));

		$sb1 = $this->createTestScoreBook();
		$sb2 = $this->createTestScoreBook();

		$this->mapper->addScoreBookToVersion($sb1->getId(), $version->getId(), 1);
		$this->mapper->addScoreBookToVersion($sb2->getId(), $version->getId(), 2);

		$this->assertSame(2, $this->mapper->countScoreBooksInVersion($version->getId()));
	}

	public function testIsScoreDirectlyInVersion(): void {
		$score = $this->createTestScore();
		$version = $this->createTestFolderCollectionVersion();

		$this->assertFalse($this->mapper->isScoreDirectlyInVersion($score->getId(), $version->getId()));

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 1);

		$this->assertTrue($this->mapper->isScoreDirectlyInVersion($score->getId(), $version->getId()));
	}

	public function testIsScoreBookInVersion(): void {
		$scoreBook = $this->createTestScoreBook();
		$version = $this->createTestFolderCollectionVersion();

		$this->assertFalse($this->mapper->isScoreBookInVersion($scoreBook->getId(), $version->getId()));

		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version->getId(), 1);

		$this->assertTrue($this->mapper->isScoreBookInVersion($scoreBook->getId(), $version->getId()));
	}

	public function testFindScoresDirectlyInVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$this->mapper->addScoreToVersion($score1->getId(), $version->getId(), 1);
		$this->mapper->addScoreToVersion($score2->getId(), $version->getId(), 2);

		$found = $this->mapper->findScoresDirectlyInVersion(
			[$score1->getId(), $score2->getId(), $score3->getId()],
			$version->getId()
		);

		$this->assertCount(2, $found);
		$this->assertContains($score1->getId(), $found);
		$this->assertContains($score2->getId(), $found);
		$this->assertNotContains($score3->getId(), $found);
	}

	public function testFindScoresDirectlyInVersionWithEmptyArray(): void {
		$version = $this->createTestFolderCollectionVersion();

		$found = $this->mapper->findScoresDirectlyInVersion([], $version->getId());

		$this->assertCount(0, $found);
		$this->assertSame([], $found);
	}

	public function testCopyLinksToVersion(): void {
		$sourceVersion = $this->createTestFolderCollectionVersion();
		$targetVersion = $this->createTestFolderCollectionVersion();

		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$scoreBook = $this->createTestScoreBook();

		$this->mapper->addScoreToVersion($score1->getId(), $sourceVersion->getId(), 1);
		$this->mapper->addScoreToVersion($score2->getId(), $sourceVersion->getId(), 2);
		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $sourceVersion->getId(), 3);

		$this->mapper->copyLinksToVersion($sourceVersion->getId(), $targetVersion->getId());

		$targetScores = $this->mapper->findScoresForVersion($targetVersion->getId());
		$targetScoreBooks = $this->mapper->findScoreBooksForVersion($targetVersion->getId());

		$this->assertCount(2, $targetScores);
		$this->assertCount(1, $targetScoreBooks);
	}

	public function testCopyLinksToVersionEmptySource(): void {
		$sourceVersion = $this->createTestFolderCollectionVersion();
		$targetVersion = $this->createTestFolderCollectionVersion();

		// No links in source
		$this->mapper->copyLinksToVersion($sourceVersion->getId(), $targetVersion->getId());

		$targetScores = $this->mapper->findScoresForVersion($targetVersion->getId());
		$this->assertCount(0, $targetScores);
	}

	public function testUniqueConstraintScorePerVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$score = $this->createTestScore();

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 1);

		// Try to add same score to same version again
		$this->expectException(Exception::class);
		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 2);
	}

	public function testUniqueConstraintScoreBookPerVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$scoreBook = $this->createTestScoreBook();

		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version->getId(), 1);

		// Try to add same score book to same version again
		$this->expectException(Exception::class);
		$this->mapper->addScoreBookToVersion($scoreBook->getId(), $version->getId(), 2);
	}

	public function testUniqueConstraintIndexPerVersion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();

		$this->mapper->addScoreToVersion($score1->getId(), $version->getId(), 1);

		// Try to add different score with same index
		$this->expectException(Exception::class);
		$this->mapper->addScoreToVersion($score2->getId(), $version->getId(), 1);
	}

	public function testCascadeDeleteOnVersionDeletion(): void {
		$version = $this->createTestFolderCollectionVersion();
		$score = $this->createTestScore();

		$this->mapper->addScoreToVersion($score->getId(), $version->getId(), 1);

		$scores = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(1, $scores);

		// Delete version - links should be cascade deleted
		$this->fcVersionMapper->delete($version);

		$scoresAfter = $this->mapper->findScoresForVersion($version->getId());
		$this->assertCount(0, $scoresAfter);
	}
}
