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
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string|null getTitleShort()
 * @method void setTitleShort(?string $value)
 * @method string|null getComposer()
 * @method void setComposer(?string $value)
 * @method string|null getArranger()
 * @method void setArranger(?string $value)
 * @method string|null getPublisher()
 * @method void setPublisher(?string $value)
 * @method int|null getYear()
 * @method void setYear(?int $value)
 * @method float|null getDifficulty()
 * @method void setDifficulty(?float $value)
 * @method string|null getDefects()
 * @method void setDefects(?string $value)
 * @method string|null getPhysicalCopiesStatus()
 * @method void setPhysicalCopiesStatus(?string $value)
 * @method string|null getDigitalStatus()
 * @method void setDigitalStatus(?string $value)
 * @method string|null getGemaIds()
 * @method string|null getMedleyContents()
 * @method int|null getDuration()
 * @method void setDuration(?int $value)
 */
class Score extends Entity implements JsonSerializable {
	protected string $title = '';
	protected ?string $titleShort = null;
	protected ?string $composer = null;
	protected ?string $arranger = null;
	protected ?string $publisher = null;
	protected ?int $year = null;
	protected ?float $difficulty = null;
	protected ?string $medleyContents = null;
	protected ?string $defects = null;
	protected ?string $physicalCopiesStatus = null;
	protected ?string $digitalStatus = null;
	protected ?string $gemaIds = null;
	protected ?int $duration = null;

	// transient properties, not stored in DB
	// ensure to add setters & getters for these
	protected ?array $tags = null;
	/** @var array{id: int, index: int}|null */
	protected ?array $scoreBook = null;

	public function __construct() {
		$this->addType('title', Types::STRING);
		$this->addType('titleShort', Types::STRING);
		$this->addType('composer', Types::STRING);
		$this->addType('arranger', Types::STRING);
		$this->addType('publisher', Types::STRING);
		$this->addType('year', Types::INTEGER);
		$this->addType('difficulty', Types::FLOAT);
		$this->addType('medleyContents', Types::STRING);
		$this->addType('defects', Types::STRING);
		$this->addType('physicalCopiesStatus', Types::STRING);
		$this->addType('digitalStatus', Types::STRING);
		$this->addType('gemaIds', Types::STRING);
		$this->addType('duration', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'titleShort' => $this->titleShort,
			'composer' => $this->composer,
			'arranger' => $this->arranger,
			'publisher' => $this->publisher,
			'year' => $this->year,
			'difficulty' => $this->difficulty,
			'medleyContents' => $this->decodeJsonArray($this->medleyContents, 'medleyContents'),
			'defects' => $this->defects,
			'physicalCopiesStatus' => $this->physicalCopiesStatus,
			'digitalStatus' => $this->digitalStatus,
			'gemaIds' => $this->decodeJsonArray($this->gemaIds, 'gemaIds'),
			'tags' => $this->tags ?? [],
			'scoreBook' => $this->scoreBook,
			'duration' => $this->duration,
		];
	}

	/**
	 * Set medley contents array.
	 *
	 * Handles JSON encoding when saving to database.
	 *
	 * @param list<string>|string|null $medleyContents Array or JSON string
	 * @psalm-suppress PossiblyUnusedMethod - Called by Entity framework and API
	 */
	public function setMedleyContents(array|string|null $medleyContents): void {
		$content = is_string($medleyContents) ? $medleyContents : $this->encodeJsonArray($medleyContents, 'medleyContents');
		$this->setter('medleyContents', [$content]);
	}

	/**
	 * Set GEMA IDs array.
	 *
	 * Handles JSON encoding when saving to database.
	 *
	 * @param list<string>|string|null $gemaIds Array or JSON string
	 * @psalm-suppress PossiblyUnusedMethod - Called by Entity framework and API
	 */
	public function setGemaIds(array|string|null $gemaIds): void {
		$content = is_string($gemaIds) ? $gemaIds : $this->encodeJsonArray($gemaIds, 'gemaIds');
		$this->setter('gemaIds', [$content]);
	}

	/**
	 * Decode a JSON field value.
	 *
	 * Handles both array (from API) and JSON string (from database) inputs.
	 *
	 * @param list<string>|string|null $value Array or JSON string
	 * @param string $fieldName Field name for error logging
	 * @return array|null Decoded array or null
	 */
	private function decodeJsonArray(array|string|null $value, string $fieldName): ?array {
		if (is_string($value)) {
			// Handle empty string as null
			if ($value === '') {
				return null;
			}
			/** @psalm-suppress MixedAssignment - json_decode with true returns mixed, but we check with is_array */
			$decoded = json_decode($value, true);
			// Check for JSON decode errors
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new \RuntimeException("Failed to decode {$fieldName} JSON: " . json_last_error_msg());
			}
			return is_array($decoded) ? $decoded : null;
		}
		return $value;
	}

	/**
	 * Encode a JSON field value for database storage.
	 *
	 * @param array|null $value Array to encode
	 * @param string $fieldName Field name for error logging
	 * @return string|null JSON string or null
	 */
	private function encodeJsonArray(?array $value, string $fieldName): ?string {
		if ($value === null) {
			return null;
		}
		$encoded = json_encode($value);
		if ($encoded === false) {
			throw new \RuntimeException("Failed to encode {$fieldName} to JSON: " . json_last_error_msg());
		}

		return $encoded;
	}

	/**
	 * Get transient tags array.
	 *
	 * @return array|null
	 * @psalm-suppress PossiblyUnusedMethod - Called via jsonSerialize
	 */
	public function getTags(): ?array {
		return $this->tags;
	}

	/**
	 * Set transient tags array.
	 *
	 * @param list<string>|null $tags
	 */
	public function setTags(?array $tags): void {
		if ($tags === null) {
			$this->tags = null;
			return;
		}
		// ensure all values are strings
		$this->tags = array_map('strval', $tags);
	}

	/**
	 * Get transient score book info (id and index).
	 *
	 * @return array{id: int, index: int}|null
	 * @psalm-suppress PossiblyUnusedMethod - Called via jsonSerialize
	 */
	public function getScoreBook(): ?array {
		return $this->scoreBook;
	}

	/**
	 * Set transient score book info (id and index).
	 *
	 * @param array{id: int, index: int}|null $scoreBook
	 */
	public function setScoreBook(?array $scoreBook): void {
		$this->scoreBook = $scoreBook;
	}
}
