<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Policy\ScorePolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ScoreScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ScoreScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_scores';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ScorePolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ScoreScope($permissionService);
	}

	protected function seed(): array {
		$scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$rows = [];
		foreach (['first', 'second'] as $title) {
			$score = new Score();
			$score->setTitle($title);
			$score = $scoreMapper->insert($score);
			$rows[$score->getId()] = $score;
		}
		return $rows;
	}
}
