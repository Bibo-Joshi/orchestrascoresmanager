<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\FolderCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the FolderCollection entity.
 *
 * Tests only custom functionality: jsonSerialize.
 */
final class FolderCollectionTest extends TestCase {
	public function testJsonSerialize(): void {
		$collection = new FolderCollection();
		$collection->setId(1);
		$collection->setTitle('Test Collection');
		$collection->setDescription('Test description');
		$collection->setCollectionType('indexed');
		$collection->setActiveVersionId(5);

		$json = $collection->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Test Collection', $json['title']);
		$this->assertSame('Test description', $json['description']);
		$this->assertSame('indexed', $json['collectionType']);
		$this->assertSame(5, $json['activeVersionId']);
	}

	public function testJsonSerializeWithNullValues(): void {
		$collection = new FolderCollection();
		$collection->setId(1);
		$collection->setTitle('Test Collection');
		$collection->setCollectionType('alphabetical');

		$json = $collection->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Test Collection', $json['title']);
		$this->assertNull($json['description']);
		$this->assertSame('alphabetical', $json['collectionType']);
		$this->assertNull($json['activeVersionId']);
	}
}
