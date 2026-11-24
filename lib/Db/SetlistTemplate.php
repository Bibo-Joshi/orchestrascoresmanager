<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use DateTimeImmutable;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Entity representing a shared PDF template for setlists.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getName()
 * @method void setName(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method string getConfigJson()
 * @method void setConfigJson(string $value)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $value)
 * @method DateTimeImmutable|null getCreatedAt()
 * @method void setCreatedAt(DateTimeImmutable $value)
 * @method DateTimeImmutable|null getUpdatedAt()
 * @method void setUpdatedAt(DateTimeImmutable $value)
 */
class SetlistTemplate extends Entity implements JsonSerializable {
	public const DEFAULT_HEADER_INFO = [
		'entries' => [
			'date',
			'startTime',
			'endTime',
			'playDuration',
			'breaksDuration',
			'overallDuration',
			'folderCollection',
		],
		'layout' => 'table',
	];

	protected string $name = '';
	protected ?string $description = null;
	protected string $configJson = '{}';
	protected string $createdBy = '';
	protected ?DateTimeImmutable $createdAt = null;
	protected ?DateTimeImmutable $updatedAt = null;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('description', Types::STRING);
		$this->addType('configJson', Types::STRING);
		$this->addType('createdBy', Types::STRING);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
		$this->addType('updatedAt', Types::DATETIME_IMMUTABLE);
	}

	public function jsonSerialize(): array {
		$config = json_decode($this->configJson, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($config) || array_is_list($config)) {
			throw new \UnexpectedValueException('Stored PDF template configuration must be a JSON object');
		}

		$fontSizes = $config['fontSizes'] ?? null;
		if ($fontSizes !== null && !is_array($fontSizes)) {
			throw new \UnexpectedValueException('Stored template fontSizes are invalid');
		}

		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'fontSizes' => empty($fontSizes) ? null : $fontSizes,
			'margin' => $config['margin'],
			'cellPadding' => $config['cellPadding'],
			'rules' => $config['rules'],
			'headerInfo' => $config['headerInfo'] ?? self::DEFAULT_HEADER_INFO,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
			'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
		];
	}
}
