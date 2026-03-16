<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial migration that creates all tables for Orchestra Scores Manager.
 *
 * Tables created:
 * - osm_scores: Main scores table
 * - osm_tags: Tags for categorization
 * - osm_scores_tags: Linking table between scores and tags
 * - osm_comments: Comments on scores
 * - osm_folder_collections: Folder collections for organizing scores
 * - osm_scores_fc: Linking table between scores/score books and folder collections
 * - osm_score_books: Score books containing multiple scores
 * - osm_score_book_scores: Linking table between score books and scores
 * - osm_scorebooks_tags: Linking table between score books and tags
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version000001000Date20251127000000 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		// Create osm_scores table
		if (!$schema->hasTable('osm_scores')) {
			$table = $schema->createTable('osm_scores');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('title_short', Types::STRING, [
				'length' => 150,
				'notnull' => false,
			]);
			$table->addColumn('composer', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('arranger', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('publisher', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('year', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('difficulty', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('medley_contents', Types::STRING, [
				'notnull' => false,
				'length' => 4000,
			]);
			$table->addColumn('defects', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('physical_copies_status', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		// Create osm_tags table
		if (!$schema->hasTable('osm_tags')) {
			$table = $schema->createTable('osm_tags');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
		}

		// Create osm_scores_tags linking table
		if (!$schema->hasTable('osm_scores_tags')) {
			$table = $schema->createTable('osm_scores_tags');
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('tag_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['score_id', 'tag_id']);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_scores_tags_sc'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_tags'),
				['tag_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_scores_tags_tag'
			);
			$table->addIndex(['tag_id'], 'idx_osm_scores_tags_tid');
		}

		// Create osm_comments table
		if (!$schema->hasTable('osm_comments')) {
			$table = $schema->createTable('osm_comments');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('content', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('creation_date', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_comments_score'
			);
			$table->addIndex(['score_id'], 'idx_osm_comments_sc_id');
		}

		// Create osm_score_books table
		if (!$schema->hasTable('osm_score_books')) {
			$table = $schema->createTable('osm_score_books');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('title_short', Types::STRING, [
				'length' => 150,
				'notnull' => false,
			]);
			$table->addColumn('composer', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('arranger', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('editor', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('publisher', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('year', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('difficulty', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('defects', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('physical_copies_status', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		// Create osm_score_book_scores linking table
		if (!$schema->hasTable('osm_score_book_scores')) {
			$table = $schema->createTable('osm_score_book_scores');
			$table->addColumn('score_book_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('index', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['score_book_id', 'score_id']);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_score_books'),
				['score_book_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_sbs_sb'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_sbs_sc'
			);
			// Index must be unique per score_book_id
			$table->addUniqueIndex(['score_book_id', 'index'], 'idx_osm_sbs_uniq_idx');
			// Score can only be in ONE score book total
			$table->addUniqueIndex(['score_id'], 'idx_osm_sbs_uniq_score');
		}

		// Create osm_scorebooks_tags linking table
		if (!$schema->hasTable('osm_scorebooks_tags')) {
			$table = $schema->createTable('osm_scorebooks_tags');
			$table->addColumn('score_book_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('tag_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['score_book_id', 'tag_id']);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_score_books'),
				['score_book_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_sbt_sb'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_tags'),
				['tag_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_sbt_t'
			);
		}

		// Create osm_folder_collections table
		if (!$schema->hasTable('osm_folder_collections')) {
			$table = $schema->createTable('osm_folder_collections');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('collection_type', Types::STRING, [
				'notnull' => true,
				'length' => 50,
			]);
			$table->setPrimaryKey(['id']);
		}

		// Create osm_scores_fc linking table
		// Uses surrogate id key since score_id is nullable (XOR with score_book_id)
		if (!$schema->hasTable('osm_scores_fc')) {
			$table = $schema->createTable('osm_scores_fc');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('score_book_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('folder_collection_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('index', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_scores_fc_sc'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_score_books'),
				['score_book_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_sfc_sb'
			);
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_folder_collections'),
				['folder_collection_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_scores_fc_fc'
			);
			$table->addIndex(['folder_collection_id'], 'idx_osm_scores_fc_fc_id');
			// Unique constraint for score per folder_collection (when score_id is not NULL)
			$table->addUniqueIndex(['folder_collection_id', 'score_id'], 'idx_osm_scores_fc_uniq_sc');
			// Unique constraint for score_book per folder_collection (when score_book_id is not NULL)
			$table->addUniqueIndex(['folder_collection_id', 'score_book_id'], 'idx_osm_sfc_uniq_sb');
			// Index must be unique per folder_collection_id (when not NULL)
			$table->addUniqueIndex(['folder_collection_id', 'index'], 'idx_osm_scores_fc_uniq');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
