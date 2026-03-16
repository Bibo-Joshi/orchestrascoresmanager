<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add digital_status column to osm_scores table.
 *
 * This column stores the digital status of a score (e.g., "Ja", "Nur digital", etc.).
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version000004000Date20251207000000 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('osm_scores')) {
			$table = $schema->getTable('osm_scores');
			if (!$table->hasColumn('digital_status')) {
				$table->addColumn('digital_status', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
