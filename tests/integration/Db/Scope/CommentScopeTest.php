<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Db\CommentMapper;
use OCA\OrchestraScoresManager\Db\Comment;
use OCA\OrchestraScoresManager\Policy\CommentPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\CommentScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class CommentScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_comments';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new CommentPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new CommentScope($permissionService);
	}

	protected function seed(): array {
		$scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$mapper = new CommentMapper($this->db);
		$score = new Score();
		$score->setTitle('Score');
		$score = $scoreMapper->insert($score);
		$rows = [];
		foreach (['first', 'second'] as $index => $content) {
			$comment = new Comment();
			$comment->setScoreId($score->getId());
			$comment->setContent($content);
			$comment->setUserId('user' . $index);
			$comment->setCreationDate(1000 + $index);
			$comment = $mapper->insert($comment);
			$rows[$comment->getId()] = $comment;
		}
		return $rows;
	}
}
