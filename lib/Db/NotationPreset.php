<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * NotationPreset entity: a named combination of a clef and a transposition, e.g. "Clarinet in Bb".
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getClefId()
 * @method void setClefId(int $value)
 * @method int getTranspositionDiatonic()
 * @method void setTranspositionDiatonic(int $value)
 * @method int getTranspositionChromatic()
 * @method void setTranspositionChromatic(int $value)
 * @method int getTranspositionOctave()
 * @method void setTranspositionOctave(int $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 */
class NotationPreset extends Entity implements JsonSerializable {
	protected int $clefId = 0;
	protected int $transpositionDiatonic = 0;
	protected int $transpositionChromatic = 0;
	protected int $transpositionOctave = 0;
	protected string $title = '';
	protected ?string $description = null;

	public function __construct() {
		$this->addType('clefId', Types::BIGINT);
		$this->addType('transpositionDiatonic', Types::INTEGER);
		$this->addType('transpositionChromatic', Types::INTEGER);
		$this->addType('transpositionOctave', Types::INTEGER);
		$this->addType('title', Types::STRING);
		$this->addType('description', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'clefId' => $this->clefId,
			'transposition' => [
				'diatonic' => $this->transpositionDiatonic,
				'chromatic' => $this->transpositionChromatic,
				'octave' => $this->transpositionOctave,
			],
			'title' => $this->title,
			'description' => $this->description,
		];
	}
}
