<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ScoreBookScope;
use OCA\OrchestraScoresManager\Policy\ScoreBookPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ScoreBookScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_score_books';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ScoreBookPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ScoreBookScope($permissionService);
	}

	protected function seed(): array {
		$tagMapper = new TagMapper($this->db);
		$mapper = new ScoreBookMapper($this->db, new ScoreBookTagLinkMapper($this->db), $tagMapper);
		$rows = [];
		foreach (['first', 'second'] as $title) {
			$book = new ScoreBook();
			$book->setTitle($title);
			$book = $mapper->insert($book);
			$rows[$book->getId()] = $book;
		}
		return $rows;
	}
}
