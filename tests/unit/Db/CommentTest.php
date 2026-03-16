<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\Comment;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the Comment entity.
 *
 * Tests only custom functionality: transient authorDisplayName property and
 * custom jsonSerialize with nested author object.
 */
final class CommentTest extends TestCase {
	public function testAuthorDisplayNameTransientProperty(): void {
		$comment = new Comment();
		$comment->setAuthorDisplayName('John Doe');

		$this->assertSame('John Doe', $comment->getAuthorDisplayName());
	}

	public function testAuthorDisplayNameIsNullByDefault(): void {
		$comment = new Comment();

		$this->assertNull($comment->getAuthorDisplayName());
	}

	public function testAuthorDisplayNameCanBeSetToNull(): void {
		$comment = new Comment();
		$comment->setAuthorDisplayName('John Doe');
		$comment->setAuthorDisplayName(null);

		$this->assertNull($comment->getAuthorDisplayName());
	}

	public function testJsonSerializeWithoutDisplayName(): void {
		$comment = new Comment();
		$comment->setId(1);
		$comment->setContent('Test comment');
		$comment->setCreationDate(1234567890);
		$comment->setUserId('user123');
		$comment->setScoreId(42);

		$json = $comment->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Test comment', $json['content']);
		$this->assertSame(1234567890, $json['creationDate']);
		$this->assertSame(42, $json['scoreId']);
		$this->assertSame('user123', $json['author']['userId']);
		$this->assertArrayNotHasKey('displayName', $json['author']);
	}

	public function testJsonSerializeWithDisplayName(): void {
		$comment = new Comment();
		$comment->setId(1);
		$comment->setContent('Test comment');
		$comment->setCreationDate(1234567890);
		$comment->setUserId('user123');
		$comment->setScoreId(42);
		$comment->setAuthorDisplayName('John Doe');

		$json = $comment->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('Test comment', $json['content']);
		$this->assertSame(1234567890, $json['creationDate']);
		$this->assertSame(42, $json['scoreId']);
		$this->assertSame('user123', $json['author']['userId']);
		$this->assertSame('John Doe', $json['author']['displayName']);
	}
}
