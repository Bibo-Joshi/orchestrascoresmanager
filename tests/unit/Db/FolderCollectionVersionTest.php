<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the FolderCollectionVersion entity.
 *
 * Tests only custom functionality: jsonSerialize with date formatting (Y-m-d).
 */
final class FolderCollectionVersionTest extends TestCase {
	public function testJsonSerializeWithBothDates(): void {
		$version = new FolderCollectionVersion();
		$validFrom = new DateTimeImmutable('2024-01-01 12:30:45');
		$validTo = new DateTimeImmutable('2024-12-31 23:59:59');

		$version->setId(1);
		$version->setFolderCollectionId(42);
		$version->setValidFrom($validFrom);
		$version->setValidTo($validTo);

		$json = $version->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame(42, $json['folderCollectionId']);
		$this->assertSame('2024-01-01', $json['validFrom']);
		$this->assertSame('2024-12-31', $json['validTo']);
	}

	public function testJsonSerializeWithNullValidTo(): void {
		$version = new FolderCollectionVersion();
		$validFrom = new DateTimeImmutable('2024-01-01');

		$version->setId(1);
		$version->setFolderCollectionId(42);
		$version->setValidFrom($validFrom);
		$version->setValidTo(null);

		$json = $version->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame(42, $json['folderCollectionId']);
		$this->assertSame('2024-01-01', $json['validFrom']);
		$this->assertNull($json['validTo']);
	}
}
