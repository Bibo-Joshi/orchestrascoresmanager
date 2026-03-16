<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * ScoreBook entity representing a book containing multiple scores.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getTitleShort()
 * @method void setTitleShort(?string $titleShort)
 * @method string|null getComposer()
 * @method void setComposer(?string $composer)
 * @method string|null getArranger()
 * @method void setArranger(?string $arranger)
 * @method string|null getEditor()
 * @method void setEditor(?string $editor)
 * @method string|null getPublisher()
 * @method void setPublisher(?string $publisher)
 * @method int|null getYear()
 * @method void setYear(?int $year)
 * @method float|null getDifficulty()
 * @method void setDifficulty(?float $difficulty)
 * @method string|null getDefects()
 * @method void setDefects(?string $defects)
 * @method string|null getPhysicalCopiesStatus()
 * @method void setPhysicalCopiesStatus(?string $physicalCopiesStatus)
 */
class ScoreBook extends Entity implements JsonSerializable {
	protected string $title = '';
	protected ?string $titleShort = null;
	protected ?string $composer = null;
	protected ?string $arranger = null;
	protected ?string $editor = null;
	protected ?string $publisher = null;
	protected ?int $year = null;
	protected ?float $difficulty = null;
	protected ?string $defects = null;
	protected ?string $physicalCopiesStatus = null;

	// transient properties, not stored in DB
	protected ?array $tags = null;

	public function __construct() {
		$this->addType('title', Types::STRING);
		$this->addType('titleShort', Types::STRING);
		$this->addType('composer', Types::STRING);
		$this->addType('arranger', Types::STRING);
		$this->addType('editor', Types::STRING);
		$this->addType('publisher', Types::STRING);
		$this->addType('year', Types::INTEGER);
		$this->addType('difficulty', Types::FLOAT);
		$this->addType('defects', Types::STRING);
		$this->addType('physicalCopiesStatus', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'titleShort' => $this->titleShort,
			'composer' => $this->composer,
			'arranger' => $this->arranger,
			'editor' => $this->editor,
			'publisher' => $this->publisher,
			'year' => $this->year,
			'difficulty' => $this->difficulty,
			'defects' => $this->defects,
			'physicalCopiesStatus' => $this->physicalCopiesStatus,
			'tags' => $this->tags ?? [],
		];
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
}
