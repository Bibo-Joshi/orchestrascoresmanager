<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\IDBConnection;

/**
 * Database access for instance-wide PDF setlist templates.
 *
 * Template identifiers are stable strings referenced by osm_setlists.
 */
class SetlistTemplateMapper {
	private const TABLE = 'osm_setlist_templates';

	/** @psalm-suppress PossiblyUnusedMethod - Constructor instantiated by Nextcloud DI */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)->orderBy('name', 'ASC')->addOrderBy('id', 'ASC');
		$rows = $qb->executeQuery()->fetchAll();
		return array_map($this->serializeRow(...), $rows);
	}

	/** @return array<string, mixed>|null */
	public function find(string $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$row = $qb->executeQuery()->fetchAssociative();
		return $row === false ? null : $this->serializeRow($row);
	}

	/** @return array<string, mixed> */
	public function create(string $id, string $name, ?string $description, array $config, string $userId): array {
		$now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id' => $qb->createNamedParameter($id),
			'name' => $qb->createNamedParameter($name),
			'description' => $qb->createNamedParameter($description),
			'config_json' => $qb->createNamedParameter(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
			'created_by' => $qb->createNamedParameter($userId),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		]);
		$qb->executeStatement();
		return $this->find($id) ?? throw new \RuntimeException('New template was not found');
	}

	/** @return array<string, mixed> */
	public function update(string $id, string $name, ?string $description, array $config): array {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('name', $qb->createNamedParameter($name))
			->set('description', $qb->createNamedParameter($description))
			->set('config_json', $qb->createNamedParameter(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
			->set('updated_at', $qb->createNamedParameter((new \DateTimeImmutable())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
		return $this->find($id) ?? throw new \RuntimeException('Updated template was not found');
	}

	public function delete(string $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/** @return array<string, mixed> */
	private function serializeRow(array $row): array {
		/** @var mixed $decoded */
		$decoded = json_decode((string)$row['config_json'], true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
			throw new \UnexpectedValueException('Stored PDF template configuration must be a JSON object');
		}
		/** @var array<string, mixed> $config */
		$config = $decoded;

		return [
			'id' => (string)$row['id'],
			'name' => (string)$row['name'],
			'description' => $row['description'] === null ? null : (string)$row['description'],
			'fontSizes' => $config['fontSizes'] ?? [],
			'margin' => $config['margin'] ?? null,
			'cellPadding' => $config['cellPadding'] ?? null,
			'rules' => $config['rules'] ?? [],
			'createdBy' => (string)$row['created_by'],
			'createdAt' => (string)$row['created_at'],
			'updatedAt' => (string)$row['updated_at'],
		];
	}
}
