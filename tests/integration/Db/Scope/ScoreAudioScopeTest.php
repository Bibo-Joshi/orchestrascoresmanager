<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreAudio;
use OCA\OrchestraScoresManager\Db\ScoreAudioMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ScoreAudioScope;
use OCA\OrchestraScoresManager\Policy\ScoreAudioPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ScoreAudioScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_score_audios';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ScoreAudioPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ScoreAudioScope($permissionService);
	}

	protected function seed(): array {
		$scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$mapper = new ScoreAudioMapper($this->db);
		$score = new Score();
		$score->setTitle('Score');
		$score = $scoreMapper->insert($score);
		$rows = [];
		foreach (['https://example.org/a.mp3', 'https://example.org/b.mp3'] as $link) {
			$audio = new ScoreAudio();
			$audio->setScoreId($score->getId());
			$audio->setLink($link);
			$audio = $mapper->insert($audio);
			$rows[$audio->getId()] = $audio;
		}
		return $rows;
	}
}
