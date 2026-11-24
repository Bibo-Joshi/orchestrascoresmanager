<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SetlistTemplate>
 */
class SetlistTemplateMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_setlist_templates', SetlistTemplate::class);
	}

	/**
	 * @return SetlistTemplate[]
	 * @throws Exception
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('name', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Delete a template and clear all setlist defaults referencing it.
	 *
	 * @throws Exception
	 */
	public function deleteAndClearDefaults(SetlistTemplate $template): void {
		$this->db->beginTransaction();
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->update('osm_setlists')
				->set('default_template_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->where($qb->expr()->eq('default_template_id', $qb->createNamedParameter($template->getId(), IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			$this->delete($template);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): SetlistTemplate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}
}
