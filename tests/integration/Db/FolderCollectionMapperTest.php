<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Integration tests for FolderCollectionMapper.
 *
 * Tests database operations for folder collection management.
 * These tests require a NextCloud environment and run in CI.
 */
final class FolderCollectionMapperTest extends MapperTestCase {
	private FolderCollectionMapper $mapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new FolderCollectionMapper($this->db);
	}

	public function testInsertWithMinimalFields(): void {
		$fc = new FolderCollection();
		$fc->setTitle('Minimal Collection');
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);

		$inserted = $this->mapper->insert($fc);

		$this->assertNotNull($inserted->getId());
		$this->assertSame('Minimal Collection', $inserted->getTitle());
		$this->assertNull($inserted->getDescription());
		$this->assertSame(FolderCollectionType::INDEXED->value, $inserted->getCollectionType());
	}

	#[TestWith([FolderCollectionType::ALPHABETICAL])]
	#[TestWith([FolderCollectionType::INDEXED])]
	public function testCollectionTypes(FolderCollectionType $type): void {
		$fc = new FolderCollection();
		$fc->setTitle('Test Collection');
		$fc->setCollectionType($type->value);

		$inserted = $this->mapper->insert($fc);
		$found = $this->mapper->find($inserted->getId());

		$this->assertSame($type->value, $found->getCollectionType());
	}

	public function testFindAll(): void {
		$fc1 = new FolderCollection();
		$fc1->setTitle('Concert 2024');
		$fc1->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		$this->mapper->insert($fc1);

		$fc2 = new FolderCollection();
		$fc2->setTitle('Practice Session');
		$fc2->setCollectionType(FolderCollectionType::INDEXED->value);
		$this->mapper->insert($fc2);

		$fc3 = new FolderCollection();
		$fc3->setTitle('Custom Mix');
		$fc3->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		$this->mapper->insert($fc3);

		$all = $this->mapper->findAll();

		$this->assertCount(3, $all);
		$titles = array_map(fn ($f) => $f->getTitle(), $all);
		$this->assertContains('Concert 2024', $titles);
		$this->assertContains('Practice Session', $titles);
		$this->assertContains('Custom Mix', $titles);
	}

	public function testFindAllWhenEmpty(): void {
		$all = $this->mapper->findAll();
		$this->assertCount(0, $all);
		$this->assertSame([], $all);
	}

	public function testUpdateDescriptionToNull(): void {
		$fc = new FolderCollection();
		$fc->setTitle('Test Collection');
		$fc->setDescription('Initial description');
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);
		$inserted = $this->mapper->insert($fc);

		$inserted->setDescription(null);
		$updated = $this->mapper->update($inserted);

		$this->assertNull($updated->getDescription());

		$found = $this->mapper->find($inserted->getId());
		$this->assertNull($found->getDescription());
	}

	public function testFindThrowsDoesNotExistException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}

	public function testLongTitleAndDescription(): void {
		$longTitle = str_repeat('A', 255);
		$longDescription = str_repeat('This is a long description. ', 1000);

		$fc = new FolderCollection();
		$fc->setTitle($longTitle);
		$fc->setDescription($longDescription);
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		$inserted = $this->mapper->insert($fc);

		$found = $this->mapper->find($inserted->getId());
		$this->assertSame($longTitle, $found->getTitle());
		$this->assertSame($longDescription, $found->getDescription());
	}

	public function testUnicodeContent(): void {
		$fc = new FolderCollection();
		$fc->setTitle('Concert 2024 – Übung für Orchester');
		$fc->setDescription('这是一个测试描述 🎵🎼🎹');
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);
		$inserted = $this->mapper->insert($fc);

		$found = $this->mapper->find($inserted->getId());
		$this->assertSame('Concert 2024 – Übung für Orchester', $found->getTitle());
		$this->assertSame('这是一个测试描述 🎵🎼🎹', $found->getDescription());
	}
}
