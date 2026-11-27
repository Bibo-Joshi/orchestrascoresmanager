<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * ConductorsScoreVariant entity representing a variant (e.g. full or condensed) of a conductor's score.
 *
 * The type is validated against ConductorsScoreVariantType by ConductorsScoreVariantService.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getConductorsScoreId()
 * @method void setConductorsScoreId(int $value)
 * @method string getType()
 * @method void setType(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 */
class ConductorsScoreVariant extends Entity implements JsonSerializable {
	protected int $conductorsScoreId = 0;
	protected string $type = '';
	protected ?string $description = null;

	public function __construct() {
		$this->addType('conductorsScoreId', Types::BIGINT);
		$this->addType('type', Types::STRING);
		$this->addType('description', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'conductorsScoreId' => $this->conductorsScoreId,
			'type' => $this->type,
			'description' => $this->description,
		];
	}
}
