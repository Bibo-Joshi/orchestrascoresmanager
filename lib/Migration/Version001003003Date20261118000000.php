<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
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
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_SQLITE) {
			return;
		}

		$schema = $schemaClosure();
		$setlists = $schema->getTable('osm_setlists');
		$prefix = $options['tablePrefix'] ?? null;
		if (!is_string($prefix)) {
			throw new \UnexpectedValueException('Missing SQLite table prefix for the setlist template migration');
		}
		$quotedPrefix = str_replace('"', '""', $prefix);
		if (!$setlists->hasColumn('default_template_id')) {
			// DBAL can rebuild the parent table when adding a foreign key,
			// which cascade-deletes setlist entries. Use an inline reference.
			$this->db->executeStatement(
				'ALTER TABLE "' . $quotedPrefix . 'osm_setlists" ADD COLUMN default_template_id VARCHAR(64) DEFAULT NULL '
				. 'CONSTRAINT fk_osm_setlists_template REFERENCES "' . $quotedPrefix . 'osm_setlist_templates" (id) ON DELETE SET NULL'
			);
		} elseif (!$this->hasDefaultTemplateForeignKey($schema, $prefix . 'osm_setlist_templates')) {
			throw new \UnexpectedValueException('SQLite default_template_id already exists without its foreign key. Restore the pre-upgrade backup before retrying.');
		}
		if (!$setlists->hasIndex('idx_osm_sl_template')) {
			$this->db->executeStatement(
				'CREATE INDEX idx_osm_sl_template ON "' . $quotedPrefix . 'osm_setlists" (default_template_id)'
			);
		}
	}

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
		}

		$templates = $schema->getTable('osm_setlist_templates');
		if (!$templates->hasIndex('idx_osm_st_name')) {
			$templates->addIndex(['name'], 'idx_osm_st_name');
		}

		$setlists = $schema->getTable('osm_setlists');
		if (!$setlists->hasColumn('default_template_id')) {
			$setlists->addColumn('default_template_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}
		if (!$setlists->hasIndex('idx_osm_sl_template')) {
			$setlists->addIndex(['default_template_id'], 'idx_osm_sl_template');
		}

		/** @var string $templateTable */
		$templateTable = $templates->getName();
		if ($this->hasDefaultTemplateForeignKey($schema, $templateTable)) {
			return $schema;
		}
		$setlists->addForeignKeyConstraint(
			$templateTable,
			['default_template_id'],
			['id'],
			['onDelete' => 'SET NULL'],
			'fk_osm_setlists_template'
		);

		return $schema;
	}
	private function hasDefaultTemplateForeignKey(ISchemaWrapper $schema, string $templateTable): bool {
		$setlists = $schema->getTable('osm_setlists');
		/** @var ForeignKeyConstraint $foreignKey */
		foreach ($setlists->getForeignKeys() as $foreignKey) {
			$columns = $foreignKey->getLocalColumns();
			if (!is_array($columns) || !in_array('default_template_id', $columns, true)) {
				continue;
			}
			if ($columns !== ['default_template_id']
				|| $foreignKey->getForeignTableName() !== $templateTable
				|| $foreignKey->getForeignColumns() !== ['id']
				|| $foreignKey->onDelete() !== 'SET NULL') {
				throw new \UnexpectedValueException('Incompatible foreign key on the setlist default template');
			}
			return true;
		}
		return false;
	}
}
