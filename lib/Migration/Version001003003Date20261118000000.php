<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Shared setlist PDF templates and optional defaults per setlist.
 *
 * Deleting a template resets affected setlists to the built-in default.
 *
 * @psalm-suppress UnusedClass - discovered by Nextcloud
 */
class Version001003003Date20261118000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('osm_setlist_templates')) {
			$table = $schema->createTable('osm_setlist_templates');
			$table->addColumn('id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 160]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('config_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['name'], 'idx_osm_st_name');
		}

		if ($schema->hasTable('osm_setlists')) {
			$table = $schema->getTable('osm_setlists');
			if (!$table->hasColumn('default_template_id')) {
				$table->addColumn('default_template_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addForeignKeyConstraint(
					$schema->getTable('osm_setlist_templates')->getName(),
					['default_template_id'],
					['id'],
					['onDelete' => 'SET NULL'],
					'fk_osm_setlists_template'
				);
				$table->addIndex(['default_template_id'], 'idx_osm_sl_template');
			}
		}

		return $schema;
	}
}
