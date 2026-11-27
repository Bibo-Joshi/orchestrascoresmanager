<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to create the tables for clefs, notation presets, parts, part variants,
 * conductor's scores and conductor's score variants.
 *
 * Constraints:
 * - osm_notation_presets.clef_id -> osm_clefs.id (RESTRICT)
 * - osm_parts.score_id -> osm_scores.id (CASCADE)
 * - osm_part_variants.part_id -> osm_parts.id (CASCADE)
 * - osm_part_variants.clef_id -> osm_clefs.id (RESTRICT)
 * - osm_part_variants.notation_preset_id -> osm_notation_presets.id (SET NULL)
 * - osm_conductors_scores.score_id -> osm_scores.id (CASCADE), unique: a score has at most one conductor's score
 * - osm_conductors_score_variants.conductors_score_id -> osm_conductors_scores.id (CASCADE)
 *
 * DBAL's schema API cannot declare CHECK constraints portably across the supported databases.
 * Therefore the following rules are enforced by the application level validation in the services only:
 * - enum values (clef sign, conductor's score variant type)
 * - the preset linked to a part variant has the same clef and transposition as the variant
 * - uniqueness of clefs (the nullable columns line and title cannot be part of a portable unique index)
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version002001000Date20261127000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('osm_clefs')) {
			$table = $schema->createTable('osm_clefs');
			$this->addIdColumn($table);
			$table->addColumn('sign', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('line', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('octave', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => false,
				'length' => 160,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('osm_notation_presets')) {
			$table = $schema->createTable('osm_notation_presets');
			$this->addIdColumn($table);
			$this->addFkColumn($table, 'clef_id', true);
			$this->addTranspositionColumns($table);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 160,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_clefs'),
				['clef_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_np_clef'
			);
			$table->addIndex(['clef_id'], 'idx_osm_np_clef_id');
		}

		if (!$schema->hasTable('osm_parts')) {
			$table = $schema->createTable('osm_parts');
			$this->addIdColumn($table);
			$this->addFkColumn($table, 'score_id', true);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 160,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_parts_score'
			);
			$table->addIndex(['score_id'], 'idx_osm_parts_score_id');
		}

		if (!$schema->hasTable('osm_part_variants')) {
			$table = $schema->createTable('osm_part_variants');
			$this->addIdColumn($table);
			$this->addFkColumn($table, 'part_id', true);
			$this->addFkColumn($table, 'clef_id', true);
			$this->addTranspositionColumns($table);
			$this->addFkColumn($table, 'notation_preset_id', false);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_parts'),
				['part_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_pv_part'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_clefs'),
				['clef_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_pv_clef'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_notation_presets'),
				['notation_preset_id'],
				['id'],
				['onDelete' => 'SET NULL'],
				'fk_osm_pv_preset'
			);
			$table->addIndex(['part_id'], 'idx_osm_pv_part_id');
			$table->addIndex(['clef_id'], 'idx_osm_pv_clef_id');
			$table->addIndex(['notation_preset_id'], 'idx_osm_pv_preset_id');
		}

		if (!$schema->hasTable('osm_conductors_scores')) {
			$table = $schema->createTable('osm_conductors_scores');
			$this->addIdColumn($table);
			$this->addFkColumn($table, 'score_id', true);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_cs_score'
			);
			$table->addUniqueIndex(['score_id'], 'uniq_osm_cs_score_id');
		}

		if (!$schema->hasTable('osm_conductors_score_variants')) {
			$table = $schema->createTable('osm_conductors_score_variants');
			$this->addIdColumn($table);
			$this->addFkColumn($table, 'conductors_score_id', true);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_conductors_scores'),
				['conductors_score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_csv_cs'
			);
			$table->addIndex(['conductors_score_id'], 'idx_osm_csv_cs_id');
		}

		return $schema;
	}

	private function addIdColumn(Table $table): void {
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
	}

	/**
	 * Add a foreign key column. It has to be of the same type as the referenced primary key.
	 */
	private function addFkColumn(Table $table, string $name, bool $notNull): void {
		$table->addColumn($name, Types::BIGINT, [
			'notnull' => $notNull,
			'length' => 20,
			'unsigned' => true,
		]);
	}

	private function addTranspositionColumns(Table $table): void {
		foreach (['transposition_diatonic', 'transposition_chromatic', 'transposition_octave'] as $column) {
			$table->addColumn($column, Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
		}
	}
}
