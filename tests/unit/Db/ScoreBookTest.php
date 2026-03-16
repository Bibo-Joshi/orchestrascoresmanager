<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\ScoreBook;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the ScoreBook entity.
 *
 * Tests only custom functionality: transient tags property (with string conversion)
 * and jsonSerialize.
 */
final class ScoreBookTest extends TestCase {
	public function testTagsTransientProperty(): void {
		$scoreBook = new ScoreBook();
		$tags = ['classical', 'symphony', 'beethoven'];

		$scoreBook->setTags($tags);

		$this->assertSame($tags, $scoreBook->getTags());
	}

	public function testTagsAreNullByDefault(): void {
		$scoreBook = new ScoreBook();

		$this->assertNull($scoreBook->getTags());
	}

	public function testTagsCanBeSetToNull(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setTags(['tag1', 'tag2']);
		$scoreBook->setTags(null);

		$this->assertNull($scoreBook->getTags());
	}

	public function testTagsAreConvertedToStrings(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setTags([1, 2, 'three']);

		$expected = ['1', '2', 'three'];
		$this->assertSame($expected, $scoreBook->getTags());
	}

	public function testJsonSerializeWithAllFields(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$scoreBook->setTitle('Complete Score Book');
		$scoreBook->setTitleShort('CSB');
		$scoreBook->setComposer('Composer Name');
		$scoreBook->setArranger('Arranger Name');
		$scoreBook->setEditor('Editor Name');
		$scoreBook->setPublisher('Publisher Name');
		$scoreBook->setYear(2024);
		$scoreBook->setDifficulty(4.0);
		$scoreBook->setDefects('Some defects');
		$scoreBook->setPhysicalCopiesStatus('Good');
		$scoreBook->setTags(['tag1', 'tag2']);

		$json = $scoreBook->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Complete Score Book', $json['title']);
		$this->assertSame('CSB', $json['titleShort']);
		$this->assertSame('Composer Name', $json['composer']);
		$this->assertSame('Arranger Name', $json['arranger']);
		$this->assertSame('Editor Name', $json['editor']);
		$this->assertSame('Publisher Name', $json['publisher']);
		$this->assertSame(2024, $json['year']);
		$this->assertSame(4.0, $json['difficulty']);
		$this->assertSame('Some defects', $json['defects']);
		$this->assertSame('Good', $json['physicalCopiesStatus']);
		$this->assertSame(['tag1', 'tag2'], $json['tags']);
	}

	public function testJsonSerializeWithMinimalFields(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$scoreBook->setTitle('Minimal Book');

		$json = $scoreBook->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Minimal Book', $json['title']);
		$this->assertNull($json['titleShort']);
		$this->assertNull($json['composer']);
		$this->assertNull($json['arranger']);
		$this->assertNull($json['editor']);
		$this->assertNull($json['publisher']);
		$this->assertNull($json['year']);
		$this->assertNull($json['difficulty']);
		$this->assertNull($json['defects']);
		$this->assertNull($json['physicalCopiesStatus']);
		$this->assertSame([], $json['tags']);
	}

	public function testJsonSerializeWithNullTags(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$scoreBook->setTitle('Book Without Tags');
		$scoreBook->setTags(null);

		$json = $scoreBook->jsonSerialize();

		$this->assertSame([], $json['tags']);
	}
}
