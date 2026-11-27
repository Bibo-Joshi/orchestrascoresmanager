<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * PartVariant entity representing one notation variant of a part.
 *
 * Invariant (kept by PartVariantService): notationPresetId is either null or references a preset
 * whose clef and transposition equal the clef and transposition of the variant.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getPartId()
 * @method void setPartId(int $value)
 * @method int getClefId()
 * @method void setClefId(int $value)
 * @method int getTranspositionDiatonic()
 * @method void setTranspositionDiatonic(int $value)
 * @method int getTranspositionChromatic()
 * @method void setTranspositionChromatic(int $value)
 * @method int getTranspositionOctave()
 * @method void setTranspositionOctave(int $value)
 * @method int|null getNotationPresetId()
 * @method void setNotationPresetId(?int $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 */
class PartVariant extends Entity implements JsonSerializable {
	protected int $partId = 0;
	protected int $clefId = 0;
	protected int $transpositionDiatonic = 0;
	protected int $transpositionChromatic = 0;
	protected int $transpositionOctave = 0;
	protected ?int $notationPresetId = null;
	protected ?string $description = null;

	public function __construct() {
		$this->addType('partId', Types::BIGINT);
		$this->addType('clefId', Types::BIGINT);
		$this->addType('transpositionDiatonic', Types::INTEGER);
		$this->addType('transpositionChromatic', Types::INTEGER);
		$this->addType('transpositionOctave', Types::INTEGER);
		$this->addType('notationPresetId', Types::BIGINT);
		$this->addType('description', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'partId' => $this->partId,
			'clefId' => $this->clefId,
			'transposition' => [
				'diatonic' => $this->transpositionDiatonic,
				'chromatic' => $this->transpositionChromatic,
				'octave' => $this->transpositionOctave,
			],
			'notationPresetId' => $this->notationPresetId,
			'description' => $this->description,
		];
	}
}
