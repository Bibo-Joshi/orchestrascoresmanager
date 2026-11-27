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
 * @extends QBMapper<Clef>
 */
class ClefMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		IDBConnection $db,
		private readonly PartVariantMapper $partVariantMapper,
		private readonly NotationPresetMapper $notationPresetMapper,
	) {
		parent::__construct($db, 'osm_clefs', Clef::class);
	}

	/**
	 * Find a clef by its ID.
	 *
	 * @param int $id
	 * @return Clef
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): Clef {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all clefs.
	 *
	 * @return array<Clef>
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
	 * Find a clef by its full notation tuple. NULL values only match NULL columns.
	 * As the database has no unique index for clefs, the clef with the lowest ID is returned if there are several.
	 *
	 * @param string $sign
	 * @param int|null $line
	 * @param int $octave
	 * @param string|null $title
	 * @return Clef|null
	 * @throws Exception
	 */
	public function findByNotation(string $sign, ?int $line, int $octave, ?string $title): ?Clef {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('sign', $qb->createNamedParameter($sign)))
			->andWhere($qb->expr()->eq('octave', $qb->createNamedParameter($octave, IQueryBuilder::PARAM_INT)))
			->andWhere($line === null
				? $qb->expr()->isNull('line')
				: $qb->expr()->eq('line', $qb->createNamedParameter($line, IQueryBuilder::PARAM_INT)))
			->andWhere($title === null
				? $qb->expr()->isNull('title')
				: $qb->expr()->eq('title', $qb->createNamedParameter($title)))
			->orderBy('id', 'ASC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * Check whether a clef is referenced by a part variant or a notation preset.
	 *
	 * @param int $id
	 * @return bool
	 * @throws Exception
	 */
	public function isInUse(int $id): bool {
		return $this->partVariantMapper->existsByClefId($id)
			|| $this->notationPresetMapper->existsByClefId($id);
	}
}
