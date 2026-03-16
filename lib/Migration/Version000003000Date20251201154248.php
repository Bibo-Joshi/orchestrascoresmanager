<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add gema_ids column to osm_scores table.
 *
 * This column stores GEMA IDs as a JSON-encoded string, similar to medley_contents.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version000003000Date20251201154248 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('osm_scores')) {
			$table = $schema->getTable('osm_scores');
			if (!$table->hasColumn('gema_ids')) {
				$table->addColumn('gema_ids', Types::STRING, [
					'notnull' => false,
					'length' => 4000,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
