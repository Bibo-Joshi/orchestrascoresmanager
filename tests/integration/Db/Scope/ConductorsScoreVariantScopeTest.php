<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Policy\ConductorsScoreVariantPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\ConductorsScoreVariantScope;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;

final class ConductorsScoreVariantScopeTest extends PartNotationScopeTestCase {
	protected function tableName(): string {
		return 'osm_conductors_score_variants';
	}

	protected function createPolicy(PermissionService $permissionService): PolicyInterface {
		return new ConductorsScoreVariantPolicy($permissionService);
	}

	protected function createScope(PermissionService $permissionService): EntityScopeInterface {
		return new ConductorsScoreVariantScope($permissionService);
	}

	protected function seed(): array {
		$rows = [];
		$mapper = new ConductorsScoreVariantMapper($this->db);
		$cs = $this->insertConductorsScore();
		foreach (['full', 'condensed'] as $type) {
			$variant = $mapper->insert(ConductorsScoreVariant::fromParams(['conductorsScoreId' => $cs->getId(), 'type' => $type]));
			$rows[$variant->getId()] = $variant;
		}
		return $rows;
	}
}
