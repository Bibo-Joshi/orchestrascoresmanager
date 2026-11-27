<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Clef entity describing a musical clef.
 *
 * The sign is validated against ClefSign by ClefService. Clefs for G, C and F have a staff line (1..5),
 * percussion and tablature clefs have no line. The octave is an octave shift, e.g. -1 for a tenor G clef.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getSign()
 * @method void setSign(string $value)
 * @method int|null getLine()
 * @method void setLine(?int $value)
 * @method int getOctave()
 * @method void setOctave(int $value)
 * @method string|null getTitle()
 * @method void setTitle(?string $value)
 */
class Clef extends Entity implements JsonSerializable {
	protected string $sign = '';
	protected ?int $line = null;
	protected int $octave = 0;
	protected ?string $title = null;

	public function __construct() {
		$this->addType('sign', Types::STRING);
		$this->addType('line', Types::INTEGER);
		$this->addType('octave', Types::INTEGER);
		$this->addType('title', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'sign' => $this->sign,
			'line' => $this->line,
			'octave' => $this->octave,
			'title' => $this->title,
		];
	}
}
