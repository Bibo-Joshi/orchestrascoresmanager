<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * ConductorsScore entity representing the conductor's score of a score. A score has at most one.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getScoreId()
 * @method void setScoreId(int $value)
 */
class ConductorsScore extends Entity implements JsonSerializable {
	protected int $scoreId = 0;

	public function __construct() {
		$this->addType('scoreId', Types::BIGINT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'scoreId' => $this->scoreId,
		];
	}
}
