<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\DB\Exception;

/**
 * Integration tests for ScoreTagLinkMapper.
 *
 * Tests link table operations, transactions, and array handling.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreTagLinkMapperTest extends MapperTestCase {
	private ScoreTagLinkMapper $mapper;
	private ScoreMapper $scoreMapper;
	private TagMapper $tagMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new ScoreTagLinkMapper($this->db);
		$this->tagMapper = new TagMapper($this->db);
		$scoreBookScoreLinkMapper = new ScoreBookScoreLinkMapper($this->db);
		$this->scoreMapper = new ScoreMapper($this->db, $this->mapper, $this->tagMapper, $scoreBookScoreLinkMapper);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}

	private function createTestTag(string $name): Tag {
		$tag = new Tag();
		$tag->setName($name);
		return $this->tagMapper->insert($tag);
	}

	public function testFindTagIdsForScore(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		// Use mapper to set tags
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());

		$this->assertCount(2, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
		$this->assertNotContains($tag3->getId(), $tagIds);
	}

	public function testFindTagIdsForScoreEmpty(): void {
		$score = $this->createTestScore();

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());

		$this->assertCount(0, $tagIds);
		$this->assertSame([], $tagIds);
	}

	public function testFindTagIdsForScores(): void {
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		// Use mapper to set tags for scores
		$this->mapper->setTagsForScore($score1->getId(), [$tag1->getId(), $tag2->getId()]);
		$this->mapper->setTagsForScore($score2->getId(), [$tag3->getId()]);

		$result = $this->mapper->findTagIdsForScores([$score1->getId(), $score2->getId()]);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey($score1->getId(), $result);
		$this->assertArrayHasKey($score2->getId(), $result);
		$this->assertCount(2, $result[$score1->getId()]);
		$this->assertCount(1, $result[$score2->getId()]);
		$this->assertContains($tag1->getId(), $result[$score1->getId()]);
		$this->assertContains($tag2->getId(), $result[$score1->getId()]);
		$this->assertContains($tag3->getId(), $result[$score2->getId()]);
	}

	public function testFindTagIdsForScoresWithEmptyArray(): void {
		$result = $this->mapper->findTagIdsForScores([]);
		$this->assertCount(0, $result);
		$this->assertSame([], $result);
	}

	public function testDeleteAllTagsForScore(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		// Use mapper to set tags
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(2, $tagIds);

		$this->mapper->deleteAllTagsForScore($score->getId());

		$tagIdsAfter = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(0, $tagIdsAfter);
	}

	public function testSetTagsForScoreAddNew(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(2, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
	}

	public function testSetTagsForScoreRemoveExisting(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		// Set initial tags
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId(), $tag3->getId()]);

		// Update to only tag1
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(1, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertNotContains($tag2->getId(), $tagIds);
		$this->assertNotContains($tag3->getId(), $tagIds);
	}

	public function testSetTagsForScoreMixed(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');
		$tag4 = $this->createTestTag('blues');

		// Set initial tags
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		// Update: remove tag2, keep tag1, add tag3, tag4
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag3->getId(), $tag4->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(3, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag3->getId(), $tagIds);
		$this->assertContains($tag4->getId(), $tagIds);
		$this->assertNotContains($tag2->getId(), $tagIds);
	}

	public function testSetTagsForScoreNoChange(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		// Set initial tags
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		// Set same tags again (no-op)
		$this->mapper->setTagsForScore($score->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(2, $tagIds);
	}

	public function testSetTagsForScoreWithStringIds(): void {
		$score = $this->createTestScore();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		// Pass IDs as strings
		$this->mapper->setTagsForScore($score->getId(), [(string)$tag1->getId(), (string)$tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(2, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
	}

	public function testCascadeDeleteOnScoreDeletion(): void {
		$score = $this->createTestScore();
		$tag = $this->createTestTag('classical');

		$this->mapper->setTagsForScore($score->getId(), [$tag->getId()]);

		$tagIds = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(1, $tagIds);

		// Delete score - links should be cascade deleted
		$this->scoreMapper->delete($score);

		$tagIdsAfter = $this->mapper->findTagIdsForScore($score->getId());
		$this->assertCount(0, $tagIdsAfter);

		// Tag should still exist
		$foundTag = $this->tagMapper->find($tag->getId());
		$this->assertNotNull($foundTag);
	}

	public function testRestrictDeleteOnTagWithLinks(): void {
		$score = $this->createTestScore();
		$tag = $this->createTestTag('classical');

		$this->mapper->setTagsForScore($score->getId(), [$tag->getId()]);

		// Try to delete tag - should fail due to RESTRICT constraint
		$this->expectException(Exception::class);
		$this->tagMapper->delete($tag);
	}

	public function testForeignKeyConstraintOnInsert(): void {
		$score = $this->createTestScore();

		// Try to set non-existent tag
		$this->expectException(Exception::class);
		$this->mapper->setTagsForScore($score->getId(), [999999]);
	}
}
