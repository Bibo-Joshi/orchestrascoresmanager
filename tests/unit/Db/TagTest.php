<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the Tag entity.
 *
 * Tests only custom functionality: setName normalization/validation and jsonSerialize.
 */
final class TagTest extends TestCase {
	public function testSetNameNormalizesToLowercase(): void {
		$tag = new Tag();
		$tag->setName('MyTag');

		$this->assertSame('mytag', $tag->getName());
	}

	public function testSetNameTrimsWhitespace(): void {
		$tag = new Tag();
		$tag->setName('  SpacedTag  ');

		$this->assertSame('spacedtag', $tag->getName());
	}

	public function testSetNameThrowsExceptionForEmptyString(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Tag name cannot be empty or whitespace only');

		$tag = new Tag();
		$tag->setName('');
	}

	public function testSetNameThrowsExceptionForWhitespaceOnly(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Tag name cannot be empty or whitespace only');

		$tag = new Tag();
		$tag->setName('   ');
	}

	public function testJsonSerialize(): void {
		$tag = new Tag();
		$tag->setId(42);
		$tag->setName('TestTag');

		$json = $tag->jsonSerialize();

		$this->assertSame(42, $json['id']);
		$this->assertSame('testtag', $json['name']);
	}
}
