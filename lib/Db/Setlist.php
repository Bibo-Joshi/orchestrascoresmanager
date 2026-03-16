<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Score entity representing a musical score.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method \DateTimeImmutable|null getStartDateTime()
 * @method void setStartDateTime(\DateTimeImmutable $value)
 * @method int|null getDuration()
 * @method void setDuration(int $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method int|null getDefaultModerationDuration()
 * @method void setDefaultModerationDuration(?int $value)
 * @method int|null getFolderCollectionVersionId()
 * @method void setFolderCollectionVersionId(?int $value)
 * @method bool getIsDraft()
 * @method void setIsDraft(bool $value)
 * @method bool getIsPublished()
 * @method void setIsPublished(bool $value)
 */
class Setlist extends Entity implements JsonSerializable {
	protected ?\DateTimeImmutable $startDateTime = null;
	protected ?int $duration = null;
	protected string $title = '';
	protected ?string $description = null;
	protected ?int $defaultModerationDuration = null;
	protected ?int $folderCollectionVersionId = null;
	protected bool $isDraft = false;
	protected bool $isPublished = false;

	public function __construct() {
		$this->addType('startDateTime', Types::DATETIME_IMMUTABLE);
		$this->addType('description', Types::STRING);
		$this->addType('title', Types::STRING);
		$this->addType('duration', Types::BIGINT);
		$this->addType('defaultModerationDuration', Types::BIGINT);
		$this->addType('folderCollectionVersionId', Types::BIGINT);
		$this->addType('isDraft', Types::BOOLEAN);
		$this->addType('isPublished', Types::BOOLEAN);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'startDateTime' => $this->startDateTime?->format(\DateTimeInterface::ATOM),
			'duration' => $this->duration,
			'defaultModerationDuration' => $this->defaultModerationDuration,
			'folderCollectionVersionId' => $this->folderCollectionVersionId,
			'isDraft' => $this->isDraft,
			'isPublished' => $this->isPublished,
		];
	}
}
