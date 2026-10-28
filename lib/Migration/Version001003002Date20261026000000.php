<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to create the osm_score_audios table for linking external audio examples to scores.
 *
 * Constraints:
 * - Foreign key score_id -> osm_scores.id (CASCADE on delete)
 *
 * An audio example has exactly one type of source: either a generic link (provider and provider_id
 * are NULL) or a known provider (provider and provider_id are set, link is NULL). This is enforced
 * by the application level validation in ScoreAudioService, as DBAL's schema API cannot declare CHECK
 * constraints portably across the supported databases.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version001003002Date20261026000000 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('osm_score_audios')) {
			$table = $schema->createTable('osm_score_audios');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('provider', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('provider_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('link', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('comment', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);

			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_score_audios_sc'
			);
			$table->addIndex(['score_id'], 'idx_osm_sa_score_id');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
