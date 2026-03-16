<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * FolderCollection entity representing a collection of scores/score books.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method string getCollectionType()
 * @method void setCollectionType(string $value)
 * @method int|null getActiveVersionId()
 * @method void setActiveVersionId(?int $value)
 */
class FolderCollection extends Entity implements JsonSerializable {
	protected string $title = '';
	protected ?string $description = null;
	protected string $collectionType = '';
	protected ?int $activeVersionId = null;

	public function __construct() {
		$this->addType('title', Types::STRING);
		$this->addType('description', Types::TEXT);
		$this->addType('collectionType', Types::STRING);
		$this->addType('activeVersionId', Types::BIGINT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'collectionType' => $this->collectionType,
			'activeVersionId' => $this->activeVersionId,
		];
	}
}
