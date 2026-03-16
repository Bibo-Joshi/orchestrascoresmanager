<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Comment;
use OCA\OrchestraScoresManager\Db\CommentMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;

/**
 * Integration tests for CommentMapper.
 *
 * Tests database operations including CRUD, foreign key constraints, and ordering.
 * These tests require a NextCloud environment and run in CI.
 */
final class CommentMapperTest extends MapperTestCase {
	private CommentMapper $mapper;
	private ScoreMapper $scoreMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new CommentMapper($this->db);

		// ScoreMapper needed for foreign key relations
		$scoreTagLinkMapper = new ScoreTagLinkMapper($this->db);
		$tagMapper = new TagMapper($this->db);
		$scoreBookScoreLinkMapper = new ScoreBookScoreLinkMapper($this->db);
		$this->scoreMapper = new ScoreMapper($this->db, $scoreTagLinkMapper, $tagMapper, $scoreBookScoreLinkMapper);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}

	public function testFindByScoreId(): void {
		$score = $this->createTestScore();

		$comment1 = new Comment();
		$comment1->setScoreId($score->getId());
		$comment1->setContent('First comment');
		$comment1->setUserId('user1');
		$comment1->setCreationDate(1000);
		$this->mapper->insert($comment1);

		$comment2 = new Comment();
		$comment2->setScoreId($score->getId());
		$comment2->setContent('Second comment');
		$comment2->setUserId('user2');
		$comment2->setCreationDate(2000);
		$this->mapper->insert($comment2);

		$comment3 = new Comment();
		$comment3->setScoreId($score->getId());
		$comment3->setContent('Third comment');
		$comment3->setUserId('user3');
		$comment3->setCreationDate(3000);
		$this->mapper->insert($comment3);

		$found = $this->mapper->findByScoreId($score->getId());

		$this->assertCount(3, $found);

		// Should be ordered by creation_date DESC (newest first)
		$this->assertSame('Third comment', $found[0]->getContent());
		$this->assertSame('Second comment', $found[1]->getContent());
		$this->assertSame('First comment', $found[2]->getContent());
	}

	public function testFindByScoreIdEmpty(): void {
		$score = $this->createTestScore();

		$found = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(0, $found);
		$this->assertSame([], $found);
	}

	public function testFindByScoreIdWithDifferentScores(): void {
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();

		$comment1 = new Comment();
		$comment1->setScoreId($score1->getId());
		$comment1->setContent('Comment for score 1');
		$comment1->setUserId('user1');
		$comment1->setCreationDate(time());
		$this->mapper->insert($comment1);

		$comment2 = new Comment();
		$comment2->setScoreId($score2->getId());
		$comment2->setContent('Comment for score 2');
		$comment2->setUserId('user2');
		$comment2->setCreationDate(time());
		$this->mapper->insert($comment2);

		$foundScore1 = $this->mapper->findByScoreId($score1->getId());
		$this->assertCount(1, $foundScore1);
		$this->assertSame('Comment for score 1', $foundScore1[0]->getContent());

		$foundScore2 = $this->mapper->findByScoreId($score2->getId());
		$this->assertCount(1, $foundScore2);
		$this->assertSame('Comment for score 2', $foundScore2[0]->getContent());
	}

	public function testDeleteByScoreId(): void {
		$score = $this->createTestScore();

		$comment1 = new Comment();
		$comment1->setScoreId($score->getId());
		$comment1->setContent('First comment');
		$comment1->setUserId('user1');
		$comment1->setCreationDate(time());
		$this->mapper->insert($comment1);

		$comment2 = new Comment();
		$comment2->setScoreId($score->getId());
		$comment2->setContent('Second comment');
		$comment2->setUserId('user2');
		$comment2->setCreationDate(time());
		$this->mapper->insert($comment2);

		$found = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(2, $found);

		$this->mapper->deleteByScoreId($score->getId());

		$foundAfter = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(0, $foundAfter);
	}

	public function testDeleteByScoreIdWithNoComments(): void {
		$score = $this->createTestScore();

		// Should not throw an exception
		$this->mapper->deleteByScoreId($score->getId());

		$found = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(0, $found);
	}

	public function testFindThrowsDoesNotExistException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}

	public function testMultipleCommentsFromSameUser(): void {
		$score = $this->createTestScore();

		for ($i = 1; $i <= 5; $i++) {
			$comment = new Comment();
			$comment->setScoreId($score->getId());
			$comment->setContent("Comment $i");
			$comment->setUserId('sameuser');
			$comment->setCreationDate(time() + $i);
			$this->mapper->insert($comment);
		}

		$found = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(5, $found);

		// All should be from same user
		foreach ($found as $comment) {
			$this->assertSame('sameuser', $comment->getUserId());
		}
	}

	public function testLongContentHandling(): void {
		$score = $this->createTestScore();

		// Create a long comment content (TEXT field should handle this)
		$longContent = str_repeat('This is a long comment. ', 1000);

		$comment = new Comment();
		$comment->setScoreId($score->getId());
		$comment->setContent($longContent);
		$comment->setUserId('testuser');
		$comment->setCreationDate(time());
		$inserted = $this->mapper->insert($comment);

		$found = $this->mapper->find($inserted->getId());
		$this->assertSame($longContent, $found->getContent());
	}

	public function testForeignKeyConstraintOnInsert(): void {
		$comment = new Comment();
		$comment->setScoreId(999999); // Non-existent score
		$comment->setContent('Test comment');
		$comment->setUserId('user1');
		$comment->setCreationDate(time());

		$this->expectException(Exception::class);
		$this->mapper->insert($comment);
	}

	public function testCascadeDeleteOnScoreDeletion(): void {
		$score = $this->createTestScore();

		$comment1 = new Comment();
		$comment1->setScoreId($score->getId());
		$comment1->setContent('Comment 1');
		$comment1->setUserId('user1');
		$comment1->setCreationDate(time());
		$this->mapper->insert($comment1);

		$comment2 = new Comment();
		$comment2->setScoreId($score->getId());
		$comment2->setContent('Comment 2');
		$comment2->setUserId('user2');
		$comment2->setCreationDate(time());
		$this->mapper->insert($comment2);

		$found = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(2, $found);

		// Delete score - comments should be cascade deleted
		$this->scoreMapper->delete($score);

		$foundAfter = $this->mapper->findByScoreId($score->getId());
		$this->assertCount(0, $foundAfter);
	}
}
