<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreAudio;
use OCA\OrchestraScoresManager\Db\ScoreAudioMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;

/**
 * Integration tests for ScoreAudioMapper.
 *
 * Tests database operations including CRUD, ordering and foreign key constraints.
 * These tests require a NextCloud environment and run in CI.
 */
final class ScoreAudioMapperTest extends MapperTestCase {
	private ScoreAudioMapper $mapper;
	private ScoreMapper $scoreMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new ScoreAudioMapper($this->db);

		// ScoreMapper needed for foreign key relations
		$scoreTagLinkMapper = new ScoreTagLinkMapper($this->db);
		$tagMapper = new TagMapper($this->db);
		$scoreBookScoreLinkMapper = new ScoreBookScoreLinkMapper($this->db);
		$this->scoreMapper = new ScoreMapper($this->db, $scoreTagLinkMapper, $tagMapper, $scoreBookScoreLinkMapper);
	}

	private function createTestScore(): Score {
		$score = new Score();
		$score->setTitle('Test Score');
		return $this->scoreMapper->insert($score);
	}

	private function createLinkAudio(int $scoreId, string $link, ?string $comment = null): ScoreAudio {
		$audio = new ScoreAudio();
		$audio->setScoreId($scoreId);
		$audio->setLink($link);
		$audio->setComment($comment);
		return $this->mapper->insert($audio);
	}

	private function createProviderAudio(int $scoreId, string $provider, string $providerId): ScoreAudio {
		$audio = new ScoreAudio();
		$audio->setScoreId($scoreId);
		$audio->setProvider($provider);
		$audio->setProviderId($providerId);
		return $this->mapper->insert($audio);
	}

	public function testInsertAndFindGenericLink(): void {
		$score = $this->createTestScore();

		$inserted = $this->createLinkAudio($score->getId(), 'https://example.org/recording.mp3', 'Live recording');

		$found = $this->mapper->find($inserted->getId());
		$this->assertSame($score->getId(), $found->getScoreId());
		$this->assertNull($found->getProvider());
		$this->assertNull($found->getProviderId());
		$this->assertSame('https://example.org/recording.mp3', $found->getLink());
		$this->assertSame('Live recording', $found->getComment());
	}

	public function testInsertAndFindProvider(): void {
		$score = $this->createTestScore();

		$inserted = $this->createProviderAudio($score->getId(), 'youtube', 'dQw4w9WgXcQ');

		$found = $this->mapper->find($inserted->getId());
		$this->assertSame('youtube', $found->getProvider());
		$this->assertSame('dQw4w9WgXcQ', $found->getProviderId());
		$this->assertNull($found->getLink());
		$this->assertNull($found->getComment());
	}

	public function testFindByScoreIdOrderedById(): void {
		$score = $this->createTestScore();

		$first = $this->createProviderAudio($score->getId(), 'youtube', 'aaaaaaaaaaa');
		$second = $this->createProviderAudio($score->getId(), 'youtube', 'bbbbbbbbbbb');
		$third = $this->createLinkAudio($score->getId(), 'https://example.org/c');

		$found = $this->mapper->findByScoreId($score->getId());

		$this->assertCount(3, $found);
		$this->assertSame($first->getId(), $found[0]->getId());
		$this->assertSame($second->getId(), $found[1]->getId());
		$this->assertSame($third->getId(), $found[2]->getId());
	}

	public function testFindByScoreIdEmpty(): void {
		$score = $this->createTestScore();

		$this->assertSame([], $this->mapper->findByScoreId($score->getId()));
	}

	public function testFindByScoreIdWithDifferentScores(): void {
		$score1 = $this->createTestScore();
		$score2 = $this->createTestScore();

		$this->createLinkAudio($score1->getId(), 'https://example.org/1');
		$this->createLinkAudio($score2->getId(), 'https://example.org/2');

		$foundScore1 = $this->mapper->findByScoreId($score1->getId());
		$this->assertCount(1, $foundScore1);
		$this->assertSame('https://example.org/1', $foundScore1[0]->getLink());

		$foundScore2 = $this->mapper->findByScoreId($score2->getId());
		$this->assertCount(1, $foundScore2);
		$this->assertSame('https://example.org/2', $foundScore2[0]->getLink());
	}

	public function testFindThrowsDoesNotExistException(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}

	public function testDelete(): void {
		$score = $this->createTestScore();
		$audio = $this->createLinkAudio($score->getId(), 'https://example.org/1');

		$this->mapper->delete($audio);

		$this->assertSame([], $this->mapper->findByScoreId($score->getId()));
	}

	public function testLongCommentHandling(): void {
		$score = $this->createTestScore();
		$longComment = str_repeat('A long comment. ', 100);

		$inserted = $this->createLinkAudio($score->getId(), 'https://example.org/1', $longComment);

		$this->assertSame($longComment, $this->mapper->find($inserted->getId())->getComment());
	}

	public function testForeignKeyConstraintOnInsert(): void {
		$audio = new ScoreAudio();
		$audio->setScoreId(999999); // Non-existent score
		$audio->setLink('https://example.org/1');

		$this->expectException(Exception::class);
		$this->mapper->insert($audio);
	}

	public function testCascadeDeleteOnScoreDeletion(): void {
		$score = $this->createTestScore();
		$this->createLinkAudio($score->getId(), 'https://example.org/1');
		$this->createProviderAudio($score->getId(), 'spotify', 'track/abc123');

		$this->assertCount(2, $this->mapper->findByScoreId($score->getId()));

		// Delete score - audio examples should be cascade deleted
		$this->scoreMapper->delete($score);

		$this->assertCount(0, $this->mapper->findByScoreId($score->getId()));
	}
}
