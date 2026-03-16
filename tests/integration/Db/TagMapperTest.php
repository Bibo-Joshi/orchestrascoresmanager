<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Integration tests for TagMapper.
 *
 * Tests database operations including CRUD operations, querying, and edge cases.
 * These tests require a NextCloud environment with a database and are designed
 * to run in CI using the PHPUnit workflow.
 */
final class TagMapperTest extends MapperTestCase {
	private TagMapper $mapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new TagMapper($this->db);
	}

	public function testFindAll(): void {
		$tag1 = new Tag();
		$tag1->setName('classical');
		$this->mapper->insert($tag1);

		$tag2 = new Tag();
		$tag2->setName('modern');
		$this->mapper->insert($tag2);

		$tag3 = new Tag();
		$tag3->setName('jazz');
		$this->mapper->insert($tag3);

		$all = $this->mapper->findAll();

		$this->assertCount(3, $all);
		$names = array_map(fn ($t) => $t->getName(), $all);
		$this->assertContains('classical', $names);
		$this->assertContains('modern', $names);
		$this->assertContains('jazz', $names);
	}

	public function testFindAllWhenEmpty(): void {
		$all = $this->mapper->findAll();
		$this->assertCount(0, $all);
		$this->assertSame([], $all);
	}

	#[DataProvider('provideNormalizedNames')]
	public function testFindByNameNormalization(string $input, string $normalized): void {
		$tag = new Tag();
		$tag->setName($normalized);
		$this->mapper->insert($tag);

		$found = $this->mapper->findByName($input);

		$this->assertNotNull($found);
		$this->assertSame($normalized, $found->getName());
	}

	public static function provideNormalizedNames(): array {
		return [
			'exact match' => ['classical', 'classical'],
			'uppercase' => ['CLASSICAL', 'classical'],
			'mixed case' => ['Classical', 'classical'],
			'with leading spaces' => ['  classical', 'classical'],
			'with trailing spaces' => ['classical  ', 'classical'],
			'with both spaces' => ['  classical  ', 'classical'],
			'unicode lowercase' => ['übung', 'übung'],
			'unicode uppercase' => ['ÜBUNG', 'übung'],
		];
	}

	public function testFindByNameNotFound(): void {
		$found = $this->mapper->findByName('nonexistent');
		$this->assertNull($found);
	}

	public function testFindByNameWithMultipleTags(): void {
		$tag1 = new Tag();
		$tag1->setName('classical');
		$this->mapper->insert($tag1);

		$tag2 = new Tag();
		$tag2->setName('modern');
		$this->mapper->insert($tag2);

		$found = $this->mapper->findByName('modern');
		$this->assertNotNull($found);
		$this->assertSame('modern', $found->getName());
	}

	public function testFindThrowsDoesNotExistException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}


	public static function provideFindMultipleIds(): array {
		return [
			'single tag' => [
				['classical', 'modern', 'jazz'],
				['classical'],
				['classical'],
			],
			'multiple tags' => [
				['classical', 'modern', 'jazz'],
				['classical', 'jazz'],
				['classical', 'jazz'],
			],
			'all tags' => [
				['classical', 'modern', 'jazz'],
				['classical', 'modern', 'jazz'],
				['classical', 'modern', 'jazz'],
			],
		];
	}

	public function testFindMultipleWithEmptyArray(): void {
		$tag = new Tag();
		$tag->setName('classical');
		$this->mapper->insert($tag);

		$found = $this->mapper->findMultiple([]);
		$this->assertCount(0, $found);
	}

	public function testFindMultipleWithNonExistentIds(): void {
		$tag = new Tag();
		$tag->setName('classical');
		$this->mapper->insert($tag);

		$found = $this->mapper->findMultiple([999999, 999998, 999997]);
		$this->assertCount(0, $found);
	}

	public function testFindMultipleWithMixedIds(): void {
		$tag1 = new Tag();
		$tag1->setName('classical');
		$inserted1 = $this->mapper->insert($tag1);

		$tag2 = new Tag();
		$tag2->setName('modern');
		$inserted2 = $this->mapper->insert($tag2);

		// Mix of existing and non-existing IDs
		$found = $this->mapper->findMultiple([$inserted1->getId(), 999999, $inserted2->getId()]);
		$this->assertCount(2, $found);

		$foundNames = array_map(fn ($t) => $t->getName(), $found);
		sort($foundNames);
		$this->assertSame(['classical', 'modern'], $foundNames);
	}

	public function testFindMultipleWithStringIds(): void {
		$tag1 = new Tag();
		$tag1->setName('classical');
		$inserted1 = $this->mapper->insert($tag1);

		$tag2 = new Tag();
		$tag2->setName('modern');
		$inserted2 = $this->mapper->insert($tag2);

		// Pass IDs as strings (should be converted to integers)
		$found = $this->mapper->findMultiple([
			(string)$inserted1->getId(),
			(string)$inserted2->getId(),
		]);

		$this->assertCount(2, $found);
		$foundNames = array_map(fn ($t) => $t->getName(), $found);
		sort($foundNames);
		$this->assertSame(['classical', 'modern'], $foundNames);
	}

	public function testFindByNameCaseInsensitive(): void {
		$tag = new Tag();
		$tag->setName('classical');
		$this->mapper->insert($tag);

		$foundLower = $this->mapper->findByName('classical');
		$foundUpper = $this->mapper->findByName('CLASSICAL');
		$foundMixed = $this->mapper->findByName('Classical');

		$this->assertNotNull($foundLower);
		$this->assertNotNull($foundUpper);
		$this->assertNotNull($foundMixed);

		$this->assertSame($foundLower->getId(), $foundUpper->getId());
		$this->assertSame($foundLower->getId(), $foundMixed->getId());
	}


	public function testConcurrentNameCollision(): void {
		// Insert first tag
		$tag1 = new Tag();
		$tag1->setName('duplicate');
		$this->mapper->insert($tag1);

		// Try to find it
		$found = $this->mapper->findByName('duplicate');
		$this->assertNotNull($found);

		// Insert another tag with same normalized name
		// This should work in DB since there's no unique constraint,
		// but findByName should throw MultipleObjectsReturnedException
		$tag2 = new Tag();
		$tag2->setName('duplicate');
		$this->mapper->insert($tag2);

		// findByName should throw because there are duplicates
		$this->expectException(MultipleObjectsReturnedException::class);
		$this->mapper->findByName('duplicate');
	}
}
