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
 * @extends QBMapper<PartVariant>
 */
class PartVariantMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_part_variants', PartVariant::class);
	}

	/**
	 * Find a part variant by its ID.
	 *
	 * @param int $id
	 * @return PartVariant
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): PartVariant {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all variants of a given part.
	 *
	 * @param int $partId
	 * @return array<PartVariant>
	 * @throws Exception
	 */
	public function findByPartId(int $partId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('part_id', $qb->createNamedParameter($partId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Delete all variants of a given part.
	 *
	 * @param int $partId
	 * @throws Exception
	 */
	public function deleteByPartId(int $partId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('part_id', $qb->createNamedParameter($partId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Delete all variants of all parts of a given score in a single statement.
	 *
	 * @param int $scoreId
	 * @throws Exception
	 */
	public function deleteByScoreId(int $scoreId): void {
		$qb = $this->db->getQueryBuilder();
		$sub = $this->db->getQueryBuilder();
		$sub->select('id')
			->from('osm_parts')
			->where($sub->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)));
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('part_id', $qb->createFunction($sub->getSQL())));
		$qb->executeStatement();
	}

	/**
	 * Remove the link to a notation preset from all variants referencing it.
	 *
	 * @param int $presetId
	 * @throws Exception
	 */
	public function unlinkNotationPreset(int $presetId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('notation_preset_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('notation_preset_id', $qb->createNamedParameter($presetId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Remove the link to the given preset from all variants whose clef or transposition no longer equal the preset's.
	 *
	 * @param NotationPreset $preset
	 * @throws Exception
	 */
	public function unlinkMismatchingForPreset(NotationPreset $preset): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('notation_preset_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('notation_preset_id', $qb->createNamedParameter($preset->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->neq('clef_id', $qb->createNamedParameter($preset->getClefId(), IQueryBuilder::PARAM_INT)),
				$qb->expr()->neq('transposition_diatonic', $qb->createNamedParameter($preset->getTranspositionDiatonic(), IQueryBuilder::PARAM_INT)),
				$qb->expr()->neq('transposition_chromatic', $qb->createNamedParameter($preset->getTranspositionChromatic(), IQueryBuilder::PARAM_INT)),
				$qb->expr()->neq('transposition_octave', $qb->createNamedParameter($preset->getTranspositionOctave(), IQueryBuilder::PARAM_INT)),
			));
		$qb->executeStatement();
	}

	/**
	 * Check whether any part variant uses the given clef.
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
