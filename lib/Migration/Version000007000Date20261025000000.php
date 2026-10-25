<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OC\DB\SchemaWrapper;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to support multiple short titles (aliases) per score.
 *
 * The single-valued title_short column of osm_scores is replaced by titles_short,
 * which stores a JSON-encoded list of strings, similar to medley_contents.
 * Existing values are converted to one-item lists.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version000007000Date20261025000000 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

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
		$schema = $schemaClosure();
		if (!$schema->hasTable('osm_scores') || !$schema->getTable('osm_scores')->hasColumn('title_short')) {
			return;
		}

		$select = $this->db->getQueryBuilder();
		$select->select('id', 'title_short')
			->from('osm_scores')
			->where($select->expr()->isNotNull('title_short'))
			->andWhere($select->expr()->neq('title_short', $select->createNamedParameter('')));

		$update = $this->db->getQueryBuilder();
		$update->update('osm_scores')
			->set('titles_short', $update->createParameter('value'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$result = $select->executeQuery();
		while ($row = $result->fetch()) {
			$update->setParameter('value', json_encode([(string)$row['title_short']], JSON_THROW_ON_ERROR), IQueryBuilder::PARAM_STR)
				->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$result->closeCursor();

		$cleanup = new SchemaWrapper($this->db);
		$cleanup->getTable('osm_scores')->dropColumn('title_short');
		$this->db->migrateToSchema($cleanup->getWrappedSchema());
	}
}
