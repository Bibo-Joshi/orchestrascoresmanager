<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\SetlistEntryScope;
use OCA\OrchestraScoresManager\Policy\Scope\SetlistScope;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Service\PermissionService;

/**
 * Entries have no policy of their own, they follow the policy of their setlist.
 */
final class SetlistEntryScopeTest extends ScopePolicyTestCase {
	protected function tableName(): string {
		return 'osm_setlist_entries';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new SetlistPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new SetlistEntryScope(new SetlistScope($permissionService), $this->db);
	}

	protected function seed(): array {
		$setlistMapper = new SetlistMapper($this->db, new SetlistScope($this->createMock(PermissionService::class)));
		$entryMapper = new SetlistEntryMapper($this->db);
		$scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$score = new Score();
		$score->setTitle('Score');
		$score = $scoreMapper->insert($score);

		$rows = [];
		foreach ([true, false] as $index => $published) {
			$setlist = new Setlist();
			$setlist->setTitle('Setlist ' . $index);
			$setlist->setIsPublished($published);
			$setlist = $setlistMapper->insert($setlist);

			$entry = new SetlistEntry();
			$entry->setSetlistId($setlist->getId());
			$entry->setIndex(0);
			$entry->setScoreId($score->getId());
			$entry = $entryMapper->insert($entry);
			$rows[$entry->getId()] = $setlist;
		}
		return $rows;
	}
}
