<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Policy\NotationPresetPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\NotationPresetScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class NotationPresetScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_notation_presets';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new NotationPresetPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new NotationPresetScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		$mapper = new NotationPresetMapper($this->db);
		$clef = $this->insertClef();
		foreach (['C', 'B flat'] as $title) {
			$preset = $mapper->insert(NotationPreset::fromParams([
				'clefId' => $clef->getId(), 'transpositionDiatonic' => 0, 'transpositionChromatic' => 0,
				'transpositionOctave' => 0, 'title' => $title,
			]));
			$rows[$preset->getId()] = $preset;
		}
		return $rows;
	}
}
