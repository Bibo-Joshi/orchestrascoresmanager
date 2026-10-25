<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration step 1/3 to support multiple short titles (aliases) per score.
 *
 * Adds the titles_short column to osm_scores, which stores a JSON-encoded list of strings,
 * similar to medley_contents. The data is copied in Version001003001Date20261025000000 and
 * the old title_short column is dropped in Version001003002Date20261025000000.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version001003000Date20261025000000 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('osm_scores')) {
			$table = $schema->getTable('osm_scores');
			if (!$table->hasColumn('titles_short')) {
				$table->addColumn('titles_short', Types::STRING, [
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
