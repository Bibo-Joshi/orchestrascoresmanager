<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\DB\Exception;

/**
 * Integration tests for ScoreBookScoreLinkMapper.
 *
 * Tests link table with index and unique constraints.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreBookScoreLinkMapperTest extends MapperTestCase {
	private ScoreBookScoreLinkMapper $mapper;
	private ScoreBookMapper $scoreBookMapper;
	private ScoreMapper $scoreMapper;

	protected function setUp(): void {
		parent::setUp();

		$tagMapper = new TagMapper($this->db);
		$scoreTagLinkMapper = new ScoreTagLinkMapper($this->db);
		$scoreBookTagLinkMapper = new ScoreBookTagLinkMapper($this->db);
		$this->mapper = new ScoreBookScoreLinkMapper($this->db);
		$this->scoreMapper = new ScoreMapper($this->db, $scoreTagLinkMapper, $tagMapper, $this->mapper);
		$this->scoreBookMapper = new ScoreBookMapper($this->db, $scoreBookTagLinkMapper, $tagMapper);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}

	private function createTestScoreBook(): ScoreBook {
		$scoreBook = new ScoreBook();
		$scoreBook->setTitle('Test Score Book');
		return $this->scoreBookMapper->insert($scoreBook);
	}

	public function testFindScoresForScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score1->getId(), 1);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score2->getId(), 2);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score3->getId(), 3);

		$scores = $this->mapper->findScoresForScoreBook($scoreBook->getId());

		$this->assertCount(3, $scores);
		// Should be ordered by index ASC
		$this->assertSame(1, $scores[0]['index']);
		$this->assertSame(2, $scores[1]['index']);
		$this->assertSame(3, $scores[2]['index']);
		$this->assertSame($score1->getId(), $scores[0]['score_id']);
		$this->assertSame($score2->getId(), $scores[1]['score_id']);
		$this->assertSame($score3->getId(), $scores[2]['score_id']);
	}

	public function testFindScoresForScoreBookEmpty(): void {
		$scoreBook = $this->createTestScoreBook();

		$scores = $this->mapper->findScoresForScoreBook($scoreBook->getId());

		$this->assertCount(0, $scores);
		$this->assertSame([], $scores);
	}

	public function testFindScoreBookForScore(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 5);

		$bookInfo = $this->mapper->findScoreBookForScore($score->getId());

		$this->assertNotNull($bookInfo);
		$this->assertSame($scoreBook->getId(), $bookInfo['score_book_id']);
		$this->assertSame(5, $bookInfo['index']);
	}

	public function testFindScoreBookForScoreNotFound(): void {
		$score = $this->createTestScore();

		$bookInfo = $this->mapper->findScoreBookForScore($score->getId());

		$this->assertNull($bookInfo);
	}

	public function testFindScoreBooksForScores(): void {
		$scoreBook1 = $this->createTestScoreBook();
		$scoreBook2 = $this->createTestScoreBook();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook1->getId(), $score1->getId(), 1);
		$this->mapper->addScoreToScoreBook($scoreBook1->getId(), $score2->getId(), 2);
		$this->mapper->addScoreToScoreBook($scoreBook2->getId(), $score3->getId(), 1);

		$result = $this->mapper->findScoreBooksForScores([$score1->getId(), $score2->getId(), $score3->getId()]);

		$this->assertCount(3, $result);
		$this->assertArrayHasKey($score1->getId(), $result);
		$this->assertArrayHasKey($score2->getId(), $result);
		$this->assertArrayHasKey($score3->getId(), $result);
		$this->assertSame($scoreBook1->getId(), $result[$score1->getId()]['score_book_id']);
		$this->assertSame($scoreBook1->getId(), $result[$score2->getId()]['score_book_id']);
		$this->assertSame($scoreBook2->getId(), $result[$score3->getId()]['score_book_id']);
	}

	public function testAddScoresToScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$scores = [
			['score_id' => $score1->getId(), 'index' => 1],
			['score_id' => $score2->getId(), 'index' => 2],
			['score_id' => $score3->getId(), 'index' => 3],
		];

		$this->mapper->addScoresToScoreBook($scoreBook->getId(), $scores);

		$found = $this->mapper->findScoresForScoreBook($scoreBook->getId());
		$this->assertCount(3, $found);
	}

	public function testRemoveScoreFromScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		$found = $this->mapper->findScoresForScoreBook($scoreBook->getId());
		$this->assertCount(1, $found);

		$this->mapper->removeScoreFromScoreBook($scoreBook->getId(), $score->getId());

		$foundAfter = $this->mapper->findScoresForScoreBook($scoreBook->getId());
		$this->assertCount(0, $foundAfter);
	}

	public function testRemoveScoreFromAllBooks(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		$bookInfo = $this->mapper->findScoreBookForScore($score->getId());
		$this->assertNotNull($bookInfo);

		$this->mapper->removeScoreFromAllBooks($score->getId());

		$bookInfoAfter = $this->mapper->findScoreBookForScore($score->getId());
		$this->assertNull($bookInfoAfter);
	}

	public function testUpdateScoreIndex(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		$bookInfo = $this->mapper->findScoreBookForScore($score->getId());
		$this->assertSame(1, $bookInfo['index']);

		$this->mapper->updateScoreIndex($scoreBook->getId(), $score->getId(), 5);

		$bookInfoAfter = $this->mapper->findScoreBookForScore($score->getId());
		$this->assertSame(5, $bookInfoAfter['index']);
	}

	public function testIsIndexOccupied(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->assertFalse($this->mapper->isIndexOccupied($scoreBook->getId(), 1));

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		$this->assertTrue($this->mapper->isIndexOccupied($scoreBook->getId(), 1));
		$this->assertFalse($this->mapper->isIndexOccupied($scoreBook->getId(), 2));
	}

	public function testCountScoresInScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();

		$this->assertSame(0, $this->mapper->countScoresInScoreBook($scoreBook->getId()));

		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$score3 = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score1->getId(), 1);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score2->getId(), 2);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score3->getId(), 3);

		$this->assertSame(3, $this->mapper->countScoresInScoreBook($scoreBook->getId()));
	}

	public function testUniqueConstraintOneScorePerBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		// Try to add same score again with different index
		$this->expectException(Exception::class);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 2);
	}

	public function testUniqueConstraintOneScoreOneBook(): void {
		$scoreBook1 = $this->createTestScoreBook();
		$scoreBook2 = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook1->getId(), $score->getId(), 1);

		// Try to add same score to different book (violates unique constraint on score_id)
		$this->expectException(Exception::class);
		$this->mapper->addScoreToScoreBook($scoreBook2->getId(), $score->getId(), 1);
	}

	public function testUniqueConstraintIndexPerBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score1->getId(), 1);

		// Try to add different score with same index
		$this->expectException(Exception::class);
		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score2->getId(), 1);
	}

	public function testCascadeDeleteOnScoreDeletion(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		$found = $this->mapper->findScoresForScoreBook($scoreBook->getId());
		$this->assertCount(1, $found);

		// Delete score - link should be cascade deleted
		$this->scoreMapper->delete($score);

		$foundAfter = $this->mapper->findScoresForScoreBook($scoreBook->getId());
		$this->assertCount(0, $foundAfter);
	}

	public function testRestrictDeleteOnScoreBookWithScores(): void {
		$scoreBook = $this->createTestScoreBook();
		$score = $this->createTestScore();

		$this->mapper->addScoreToScoreBook($scoreBook->getId(), $score->getId(), 1);

		// Try to delete score book with scores - should fail due to RESTRICT
		$this->expectException(Exception::class);
		$this->scoreBookMapper->delete($scoreBook);
	}
}
