<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create shared PDF templates and optional defaults for setlists.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version001003003Date20261118000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('osm_setlist_templates')) {
			$table = $schema->createTable('osm_setlist_templates');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 160,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('config_json', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		$setlists = $schema->getTable('osm_setlists');

		if (!$setlists->hasColumn('default_template_id')) {
			$setlists->addColumn('default_template_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
