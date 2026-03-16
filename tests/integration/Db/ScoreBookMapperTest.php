<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Integration tests for ScoreBookMapper.
 *
 * Tests mapper with tag attachment functionality.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreBookMapperTest extends MapperTestCase {
	private ScoreBookMapper $mapper;
	private TagMapper $tagMapper;
	private ScoreBookTagLinkMapper $linkMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->tagMapper = new TagMapper($this->db);
		$this->linkMapper = new ScoreBookTagLinkMapper($this->db);
		$this->mapper = new ScoreBookMapper($this->db, $this->linkMapper, $this->tagMapper);
	}

	private function createTestTag(string $name): Tag {
		$tag = new Tag();
		$tag->setName($name);
		return $this->tagMapper->insert($tag);
	}

	public function testFindAttachesTags(): void {
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');

		$scoreBook = new ScoreBook();
		$scoreBook->setTitle('Test Book');
		$inserted = $this->mapper->insert($scoreBook);

		// Add tags
		$this->linkMapper->setTagsForScoreBook($inserted->getId(), [$tag1->getId(), $tag2->getId()]);

		$found = $this->mapper->find($inserted->getId());

		$tags = $found->getTags();
		$this->assertNotNull($tags);
		$this->assertCount(2, $tags);
		$this->assertContains('classical', $tags);
		$this->assertContains('modern', $tags);
	}

	public function testFindAllAttachesTags(): void {
		$tag1 = $this->createTestTag('classical');
		$tag2 = $this->createTestTag('modern');
		$tag3 = $this->createTestTag('jazz');

		$sb1 = new ScoreBook();
		$sb1->setTitle('Book 1');
		$inserted1 = $this->mapper->insert($sb1);
		$this->linkMapper->setTagsForScoreBook($inserted1->getId(), [$tag1->getId(), $tag2->getId()]);

		$sb2 = new ScoreBook();
		$sb2->setTitle('Book 2');
		$inserted2 = $this->mapper->insert($sb2);
		$this->linkMapper->setTagsForScoreBook($inserted2->getId(), [$tag3->getId()]);

		$all = $this->mapper->findAll();

		$this->assertCount(2, $all);

		// Find book 1 in results
		$book1 = null;
		$book2 = null;
		foreach ($all as $book) {
			if ($book->getId() === $inserted1->getId()) {
				$book1 = $book;
			} elseif ($book->getId() === $inserted2->getId()) {
				$book2 = $book;
			}
		}

		$this->assertNotNull($book1);
		$this->assertNotNull($book2);
		$this->assertCount(2, $book1->getTags());
		$this->assertCount(1, $book2->getTags());
	}

	public function testFindMultiple(): void {
		$tag = $this->createTestTag('classical');

		$sb1 = new ScoreBook();
		$sb1->setTitle('Book 1');
		$inserted1 = $this->mapper->insert($sb1);
		$this->linkMapper->setTagsForScoreBook($inserted1->getId(), [$tag->getId()]);

		$sb2 = new ScoreBook();
		$sb2->setTitle('Book 2');
		$inserted2 = $this->mapper->insert($sb2);

		$found = $this->mapper->findMultiple([$inserted1->getId(), $inserted2->getId()]);

		$this->assertCount(2, $found);
		// Tags should be attached
		foreach ($found as $book) {
			$this->assertNotNull($book->getTags());
		}
	}

	public function testFindMultipleWithEmptyArray(): void {
		$found = $this->mapper->findMultiple([]);
		$this->assertCount(0, $found);
		$this->assertSame([], $found);

	}

	public function testFindAllWhenEmpty(): void {
		$all = $this->mapper->findAll();
		$this->assertCount(0, $all);
		$this->assertSame([], $all);
	}

	public function testFindThrowsDoesNotExistException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}
}
