<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\ScoreAudio;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the ScoreAudio entity.
 *
 * Tests only custom functionality: defaults, fromParams mapping and custom jsonSerialize.
 */
final class ScoreAudioTest extends TestCase {
	public function testDefaults(): void {
		$audio = new ScoreAudio();

		$this->assertSame(0, $audio->getScoreId());
		$this->assertNull($audio->getProvider());
		$this->assertNull($audio->getProviderId());
		$this->assertNull($audio->getLink());
		$this->assertNull($audio->getComment());
	}

	public function testFromParamsGenericLink(): void {
		$audio = ScoreAudio::fromParams([
			'scoreId' => 7,
			'link' => 'https://example.org/recording.mp3',
			'comment' => 'Live recording',
		]);

		$this->assertSame(7, $audio->getScoreId());
		$this->assertSame('https://example.org/recording.mp3', $audio->getLink());
		$this->assertSame('Live recording', $audio->getComment());
		$this->assertNull($audio->getProvider());
		$this->assertNull($audio->getProviderId());
	}

	public function testFromParamsProvider(): void {
		$audio = ScoreAudio::fromParams([
			'scoreId' => 7,
			'provider' => 'youtube',
			'providerId' => 'dQw4w9WgXcQ',
		]);

		$this->assertSame('youtube', $audio->getProvider());
		$this->assertSame('dQw4w9WgXcQ', $audio->getProviderId());
		$this->assertNull($audio->getLink());
		$this->assertNull($audio->getComment());
	}

	public function testJsonSerializeGenericLink(): void {
		$audio = new ScoreAudio();
		$audio->setId(1);
		$audio->setScoreId(42);
		$audio->setLink('https://example.org/recording.mp3');
		$audio->setComment('A comment');

		$this->assertSame([
			'id' => 1,
			'scoreId' => 42,
			'provider' => null,
			'providerId' => null,
			'link' => 'https://example.org/recording.mp3',
			'comment' => 'A comment',
		], $audio->jsonSerialize());
	}

	public function testJsonSerializeProvider(): void {
		$audio = new ScoreAudio();
		$audio->setId(2);
		$audio->setScoreId(42);
		$audio->setProvider('spotify');
		$audio->setProviderId('track/abc123');

		$this->assertSame([
			'id' => 2,
			'scoreId' => 42,
			'provider' => 'spotify',
			'providerId' => 'track/abc123',
			'link' => null,
			'comment' => null,
		], $audio->jsonSerialize());
	}
}
