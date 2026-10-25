<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration step 2/3 to support multiple short titles (aliases) per score.
 *
 * Copies the values of osm_scores.title_short into osm_scores.titles_short as one-item JSON lists.
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version001003001Date20261025000000 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
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
	}
}
