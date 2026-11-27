<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Part entity representing an instrument part of a score.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getScoreId()
 * @method void setScoreId(int $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 */
class Part extends Entity implements JsonSerializable {
	protected int $scoreId = 0;
	protected string $title = '';
	protected ?string $description = null;

	public function __construct() {
		$this->addType('scoreId', Types::BIGINT);
		$this->addType('title', Types::STRING);
		$this->addType('description', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'scoreId' => $this->scoreId,
			'title' => $this->title,
			'description' => $this->description,
		];
	}
}
