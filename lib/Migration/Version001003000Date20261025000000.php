<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration step 1/2 to support multiple short titles (aliases) per score.
 *
 * Adds the titles_short column to osm_scores, which stores a JSON-encoded list of strings,
 * similar to medley_contents, and copies the values of title_short into it as one-item lists.
 * The old title_short column is dropped in Version001003001Date20261025000000.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version001003000Date20261025000000 extends SimpleMigrationStep {

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
			$update->setParameter('value', json_encode([(string)$row['title_short']], JSON_THROW_ON_ERROR))
				->setParameter('id', (int)$row['id'])
				->executeStatement();
		}
		$result->closeCursor();
	}
}
