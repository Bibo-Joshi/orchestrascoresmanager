<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\DB\Exception;

/**
 * Integration tests for ScoreBookTagLinkMapper.
 *
 * Tests link table operations for score book to tag relationships.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreBookTagLinkMapperTest extends MapperTestCase {
	private ScoreBookTagLinkMapper $mapper;
	private ScoreBookMapper $scoreBookMapper;
	private TagMapper $tagMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new ScoreBookTagLinkMapper($this->db);
		$this->tagMapper = new TagMapper($this->db);
		$this->scoreBookMapper = new ScoreBookMapper($this->db, $this->mapper, $this->tagMapper);
	}

	private function createTestScoreBook(): ScoreBook {
		$scoreBook = new ScoreBook();
		$scoreBook->setTitle('Test Score Book');
		return $this->scoreBookMapper->insert($scoreBook);
	}

	private function createTestTag(string $name): Tag {
		$tag = new Tag();
		$tag->setName($name);
		return $this->tagMapper->insert($tag);
	}

	public function testFindTagIdsForScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());

		$this->assertCount(2, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
	}

	public function testFindTagIdsForScoreBookEmpty(): void {
		$scoreBook = $this->createTestScoreBook();

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());

		$this->assertCount(0, $tagIds);
		$this->assertSame([], $tagIds);
	}

	public function testFindTagIdsForScoreBooks(): void {
		$sb1 = $this->createTestScoreBook();
		$sb2 = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		// Use mapper to set up test data
		$this->mapper->setTagsForScoreBook($sb1->getId(), [$tag1->getId(), $tag2->getId()]);
		$this->mapper->setTagsForScoreBook($sb2->getId(), [$tag3->getId()]);

		$result = $this->mapper->findTagIdsForScoreBooks([$sb1->getId(), $sb2->getId()]);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey($sb1->getId(), $result);
		$this->assertArrayHasKey($sb2->getId(), $result);
		$this->assertCount(2, $result[$sb1->getId()]);
		$this->assertCount(1, $result[$sb2->getId()]);
	}

	public function testFindTagIdsForScoreBooksEmpty(): void {
		$result = $this->mapper->findTagIdsForScoreBooks([]);
		$this->assertCount(0, $result);
		$this->assertSame([], $result);
	}

	public function testDeleteAllTagsForScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(2, $tagIds);

		$this->mapper->deleteAllTagsForScoreBook($scoreBook->getId());

		$tagIdsAfter = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(0, $tagIdsAfter);
	}

	public function testSetTagsForScoreBook(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(2, $tagIds);
		$this->assertContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
	}

	public function testSetTagsForScoreBookUpdate(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		// Update to different tags
		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag2->getId(), $tag3->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(2, $tagIds);
		$this->assertNotContains($tag1->getId(), $tagIds);
		$this->assertContains($tag2->getId(), $tagIds);
		$this->assertContains($tag3->getId(), $tagIds);
	}

	public function testSetTagsForScoreBookNoChange(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		// Set same tags (should be no-op)
		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag1->getId(), $tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(2, $tagIds);
	}

	public function testSetTagsWithStringIds(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		// Pass IDs as strings
		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [(string)$tag1->getId(), (string)$tag2->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(2, $tagIds);
	}

	public function testCascadeDeleteOnScoreBookDeletion(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag = $this->createTestTag('classical');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(1, $tagIds);

		// Delete score book - links should be cascade deleted
		$this->scoreBookMapper->delete($scoreBook);

		$tagIdsAfter = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(0, $tagIdsAfter);

		// Tag should still exist
		$foundTag = $this->tagMapper->find($tag->getId());
		$this->assertNotNull($foundTag);
	}

	public function testCascadeDeleteOnTagDeletion(): void {
		$scoreBook = $this->createTestScoreBook();
		$tag = $this->createTestTag('classical');

		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [$tag->getId()]);

		$tagIds = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(1, $tagIds);

		// Delete tag - links should be cascade deleted (different from scores_tags!)
		$this->tagMapper->delete($tag);

		$tagIdsAfter = $this->mapper->findTagIdsForScoreBook($scoreBook->getId());
		$this->assertCount(0, $tagIdsAfter);
	}

	public function testForeignKeyConstraintInvalid(): void {
		$scoreBook = $this->createTestScoreBook();

		// Try to set non-existent tag
		$this->expectException(Exception::class);
		$this->mapper->setTagsForScoreBook($scoreBook->getId(), [999999]);

	}
}
