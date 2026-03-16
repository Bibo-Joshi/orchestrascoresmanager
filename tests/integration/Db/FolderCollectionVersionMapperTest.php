<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * Integration tests for FolderCollectionVersionMapper.
 *
 * Tests version management, date range validation, and overlap detection.
 * These tests require a NextCloud environment and run in CI.
 */
final class FolderCollectionVersionMapperTest extends MapperTestCase {
	private FolderCollectionVersionMapper $mapper;
	private FolderCollectionMapper $fcMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new FolderCollectionVersionMapper($this->db);
		$this->fcMapper = new FolderCollectionMapper($this->db);
	}

	private function createTestFolderCollection(): FolderCollection {
		$fc = new FolderCollection();
		$fc->setTitle('Test Collection');
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);
		return $this->fcMapper->insert($fc);
	}

	public function testInsertWithOpenEndedValidTo(): void {
		$fc = $this->createTestFolderCollection();

		$version = new FolderCollectionVersion();
		$version->setFolderCollectionId($fc->getId());
		$version->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$version->setValidTo(null); // Open-ended

		$inserted = $this->mapper->insert($version);

		$this->assertNull($inserted->getValidTo());
	}

	public function testFindAllForFolderCollection(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		$v2 = new FolderCollectionVersion();
		$v2->setFolderCollectionId($fc->getId());
		$v2->setValidFrom(new DateTimeImmutable('2024-07-01'));
		$v2->setValidTo(new DateTimeImmutable('2024-12-31'));
		$this->mapper->insert($v2);

		$v3 = new FolderCollectionVersion();
		$v3->setFolderCollectionId($fc->getId());
		$v3->setValidFrom(new DateTimeImmutable('2025-01-01'));
		$v3->setValidTo(null);
		$this->mapper->insert($v3);

		$all = $this->mapper->findAllForFolderCollection($fc->getId());

		$this->assertCount(3, $all);

		// Should be ordered by valid_from DESC (newest first)
		$this->assertEquals(new DateTimeImmutable('2025-01-01'), $all[0]->getValidFrom());
		$this->assertEquals(new DateTimeImmutable('2024-07-01'), $all[1]->getValidFrom());
		$this->assertEquals(new DateTimeImmutable('2024-01-01'), $all[2]->getValidFrom());
	}

	public function testFindActiveVersion(): void {
		$fc = $this->createTestFolderCollection();

		// Closed version (not active)
		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		// Active version (valid_to is null)
		$v2 = new FolderCollectionVersion();
		$v2->setFolderCollectionId($fc->getId());
		$v2->setValidFrom(new DateTimeImmutable('2024-07-01'));
		$v2->setValidTo(null);
		$this->mapper->insert($v2);

		$active = $this->mapper->findActiveVersion($fc->getId());

		$this->assertNotNull($active);
		$this->assertEquals(new DateTimeImmutable('2024-07-01'), $active->getValidFrom());
		$this->assertNull($active->getValidTo());
	}

	public function testFindActiveVersionWhenNoneExists(): void {
		$fc = $this->createTestFolderCollection();

		// Only closed versions
		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-12-31'));
		$this->mapper->insert($v1);

		$active = $this->mapper->findActiveVersion($fc->getId());

		$this->assertNull($active);
	}

	public function testFindActiveVersionThrowsOnMultiple(): void {
		$fc = $this->createTestFolderCollection();

		// Two active versions - invalid state
		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(null);
		$this->mapper->insert($v1);

		$v2 = new FolderCollectionVersion();
		$v2->setFolderCollectionId($fc->getId());
		$v2->setValidFrom(new DateTimeImmutable('2024-07-01'));
		$v2->setValidTo(null);
		$this->mapper->insert($v2);

		$this->expectException(MultipleObjectsReturnedException::class);
		$this->mapper->findActiveVersion($fc->getId());
	}

	public function testFindLatestVersion(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		$v2 = new FolderCollectionVersion();
		$v2->setFolderCollectionId($fc->getId());
		$v2->setValidFrom(new DateTimeImmutable('2024-07-01'));
		$v2->setValidTo(new DateTimeImmutable('2024-12-31'));
		$this->mapper->insert($v2);

		$v3 = new FolderCollectionVersion();
		$v3->setFolderCollectionId($fc->getId());
		$v3->setValidFrom(new DateTimeImmutable('2025-01-01'));
		$v3->setValidTo(null);
		$this->mapper->insert($v3);

		$latest = $this->mapper->findLatestVersion($fc->getId());

		$this->assertEquals(new DateTimeImmutable('2025-01-01'), $latest->getValidFrom());
	}

	public function testFindLatestVersionThrowsWhenNoneExists(): void {
		$fc = $this->createTestFolderCollection();

		$this->expectException(DoesNotExistException::class);
		$this->mapper->findLatestVersion($fc->getId());
	}

	public function testHasOverlappingVersionWithNoOverlap(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		// New version that doesn't overlap
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-07-01'),
			new DateTimeImmutable('2024-12-31')
		);

		$this->assertFalse($hasOverlap);
	}

	public function testHasOverlappingVersionWithOverlap(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		// New version that overlaps
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-06-01'), // Overlaps existing
			new DateTimeImmutable('2024-07-31')
		);

		$this->assertTrue($hasOverlap);
	}

	public function testHasOverlappingVersionWithOpenEndedExisting(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(null); // Open-ended
		$this->mapper->insert($v1);

		// Any future version will overlap with open-ended
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2025-01-01'),
			new DateTimeImmutable('2025-12-31')
		);

		$this->assertTrue($hasOverlap);
	}

	public function testHasOverlappingVersionWithOpenEndedNew(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-12-31'));
		$this->mapper->insert($v1);

		// New open-ended version starting before existing ends
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-06-01'),
			null // Open-ended
		);

		$this->assertTrue($hasOverlap);
	}

	public function testHasOverlappingVersionExcludesSpecifiedVersion(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-12-31'));
		$inserted = $this->mapper->insert($v1);

		// Check for overlap excluding the existing version itself
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-01-01'),
			new DateTimeImmutable('2024-12-31'),
			$inserted->getId() // Exclude this version
		);

		$this->assertFalse($hasOverlap);
	}

	public function testDeleteAllForFolderCollection(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		$v2 = new FolderCollectionVersion();
		$v2->setFolderCollectionId($fc->getId());
		$v2->setValidFrom(new DateTimeImmutable('2024-07-01'));
		$v2->setValidTo(null);
		$this->mapper->insert($v2);

		$all = $this->mapper->findAllForFolderCollection($fc->getId());
		$this->assertCount(2, $all);

		$this->mapper->deleteAllForFolderCollection($fc->getId());

		$allAfter = $this->mapper->findAllForFolderCollection($fc->getId());
		$this->assertCount(0, $allAfter);
	}

	public function testEdgeCaseSameDayBoundary(): void {
		$fc = $this->createTestFolderCollection();

		$v1 = new FolderCollectionVersion();
		$v1->setFolderCollectionId($fc->getId());
		$v1->setValidFrom(new DateTimeImmutable('2024-01-01'));
		$v1->setValidTo(new DateTimeImmutable('2024-06-30'));
		$this->mapper->insert($v1);

		// New version starting on the day after previous ends
		$hasOverlap = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-07-01'),
			new DateTimeImmutable('2024-12-31')
		);

		$this->assertFalse($hasOverlap);

		// New version starting on the same day previous ends
		$hasOverlapSameDay = $this->mapper->hasOverlappingVersion(
			$fc->getId(),
			new DateTimeImmutable('2024-06-30'),
			new DateTimeImmutable('2024-12-31')
		);

		$this->assertTrue($hasOverlapSameDay);
	}
}
