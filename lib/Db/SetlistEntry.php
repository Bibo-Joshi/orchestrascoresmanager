<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * SetlistEntry entity representing an entry in a setlist (either a score or a break).
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getSetlistId()
 * @method void setSetlistId(int $value)
 * @method int getIndex()
 * @method void setIndex(int $value)
 * @method string|null getComment()
 * @method void setComment(?string $value)
 * @method int|null getModerationDuration()
 * @method void setModerationDuration(?int $value)
 * @method int|null getBreakDuration()
 * @method void setBreakDuration(?int $value)
 * @method int|null getScoreId()
 * @method void setScoreId(?int $value)
 */
class SetlistEntry extends Entity implements JsonSerializable {
	protected int $setlistId = 0;
	protected int $index = 0;
	protected ?string $comment = null;
	protected ?int $moderationDuration = null;

	// breaks
	protected ?int $breakDuration = null;

	// scores
	protected ?int $scoreId = null;

	public function __construct() {
		$this->addType('setlistId', Types::BIGINT);
		$this->addType('index', Types::BIGINT);
		$this->addType('comment', Types::STRING);
		$this->addType('moderationDuration', Types::BIGINT);
		$this->addType('breakDuration', Types::BIGINT);
		$this->addType('scoreId', Types::BIGINT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'setlistId' => $this->setlistId,
			'index' => $this->index,
			'comment' => $this->comment,
			'moderationDuration' => $this->moderationDuration,
			'breakDuration' => $this->breakDuration,
			'scoreId' => $this->scoreId,
		];
	}
}
