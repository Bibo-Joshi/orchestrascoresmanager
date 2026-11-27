<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\PartVariant;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Policy\PartVariantPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Policy\Scope\PartVariantScope;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class PartVariantScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_part_variants';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new PartVariantPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new PartVariantScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		$mapper = new PartVariantMapper($this->db);
		$part = $this->insertPart();
		$clef = $this->insertClef();
		for ($i = 0; $i < 2; $i++) {
			$variant = $mapper->insert(PartVariant::fromParams([
				'partId' => $part->getId(), 'clefId' => $clef->getId(), 'transpositionDiatonic' => $i,
				'transpositionChromatic' => 0, 'transpositionOctave' => 0, 'notationPresetId' => null,
			]));
			$rows[$variant->getId()] = $variant;
		}
		return $rows;
	}
}
