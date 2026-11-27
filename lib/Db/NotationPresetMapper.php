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
 * @extends QBMapper<NotationPreset>
 */
class NotationPresetMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_notation_presets', NotationPreset::class);
	}

	/**
	 * Find a notation preset by its ID.
	 *
	 * @param int $id
	 * @return NotationPreset
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): NotationPreset {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all notation presets.
	 *
	 * @return array<NotationPreset>
	 * @throws Exception
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Find all notation presets with the given clef and transposition.
	 *
	 * @param int $clefId
	 * @param int $diatonic
	 * @param int $chromatic
	 * @param int $octave
	 * @return array<NotationPreset>
	 * @throws Exception
	 */
	public function findByClefAndTransposition(int $clefId, int $diatonic, int $chromatic, int $octave): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('clef_id', $qb->createNamedParameter($clefId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('transposition_diatonic', $qb->createNamedParameter($diatonic, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('transposition_chromatic', $qb->createNamedParameter($chromatic, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('transposition_octave', $qb->createNamedParameter($octave, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Check whether any notation preset uses the given clef.
	 *
	 * @param int $clefId
	 * @return bool
	 * @throws Exception
	 */
	public function existsByClefId(int $clefId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('clef_id', $qb->createNamedParameter($clefId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}
}
