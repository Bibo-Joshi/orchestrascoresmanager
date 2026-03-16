<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use DateTimeImmutable;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Entity representing a version of a folder collection.
 *
 * A folder collection can have multiple versions over time.
 * At most one version can be active (valid_to = null) at any time.
 *
 * Note: validFrom and validTo are DATE fields (not datetime), representing
 * day-level precision only. They use DateTimeImmutable in PHP (as there is
 * no separate DateImmutable class) but are always normalized to midnight UTC
 * and serialized as Y-m-d format.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getFolderCollectionId()
 * @method void setFolderCollectionId(int $value)
 * @method DateTimeImmutable getValidFrom() Get start date (normalized to midnight UTC)
 * @method void setValidFrom(DateTimeImmutable $value) Set start date (should be normalized to midnight UTC)
 * @method DateTimeImmutable|null getValidTo() Get end date (normalized to midnight UTC, null = active/open-ended)
 * @method void setValidTo(?DateTimeImmutable $value) Set end date (should be normalized to midnight UTC, null = active/open-ended)
 */
class FolderCollectionVersion extends Entity implements JsonSerializable {
	protected int $folderCollectionId = 0;
	/** @var DateTimeImmutable|null Start date of version validity (DATE field, normalized to midnight UTC) */
	protected ?DateTimeImmutable $validFrom = null;
	/** @var DateTimeImmutable|null End date of version validity (DATE field, normalized to midnight UTC, null = active) */
	protected ?DateTimeImmutable $validTo = null;

	public function __construct() {
		$this->addType('folderCollectionId', Types::BIGINT);
		$this->addType('validFrom', Types::DATE_IMMUTABLE);
		$this->addType('validTo', Types::DATE_IMMUTABLE);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'folderCollectionId' => $this->folderCollectionId,
			'validFrom' => $this->validFrom?->format('Y-m-d'),
			'validTo' => $this->validTo?->format('Y-m-d'),
		];
	}
}
