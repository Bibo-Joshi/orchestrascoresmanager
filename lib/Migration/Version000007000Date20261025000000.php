<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to support multiple short titles (aliases) per score.
 *
 * The title_short column of osm_scores is widened and its existing values are
 * converted to JSON-encoded lists of strings, similar to medley_contents.
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
			if ($table->hasColumn('title_short')) {
				$table->changeColumn('title_short', [
					'length' => 4000,
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$select = $this->db->getQueryBuilder();
		$select->select('id', 'title_short')
			->from('osm_scores')
			->where($select->expr()->isNotNull('title_short'));

		$update = $this->db->getQueryBuilder();
		$update->update('osm_scores')
			->set('title_short', $update->createParameter('value'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$result = $select->executeQuery();
		while ($row = $result->fetch()) {
			$value = (string)$row['title_short'];
			if ($value === '') {
				$encoded = null;
			} else {
				$decoded = json_decode($value, true);
				if (is_array($decoded) && array_is_list($decoded)) {
					continue;
				}
				$encoded = json_encode([$value], JSON_THROW_ON_ERROR);
			}
			$update->setParameter('value', $encoded, $encoded === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR)
				->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$result->closeCursor();
	}
}
