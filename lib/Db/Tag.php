<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Tag entity representing a tag for categorization.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getName()
 */
class Tag extends Entity implements JsonSerializable {
	protected string $name = '';

	public function __construct() {
		$this->addType('name', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
		];
	}

	/**
	 * Set the tag name (will be normalized to lowercase).
	 *
	 * @param string $name The tag name
	 * @throws \InvalidArgumentException If name is empty or whitespace only
	 * @psalm-suppress PossiblyUnusedMethod - Called by Entity::fromParams
	 */
	public function setName(string $name): void {
		// enforce lowercase tags
		$normalized = mb_strtolower(trim($name));
		if ($normalized === '') {
			throw new \InvalidArgumentException('Tag name cannot be empty or whitespace only');
		}
		// delegate to Entity.setName. Important to ensure DB updates work correctly.
		$this->setter('name', [$normalized]);
	}
}
