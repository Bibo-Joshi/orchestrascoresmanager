<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db\Enum;

/**
 * Supported clef signs.
 *
 * The sign is stored as a plain string in the database and validated by ClefService.
 */
enum ClefSign: string {
	case G = 'G';
	case C = 'C';
	case F = 'F';
	case PERCUSSION = 'percussion';
	case TAB = 'TAB';

	/**
	 * Whether clefs with this sign are placed on a staff line (1..5).
	 * Percussion and tablature clefs have no line.
	 */
	public function hasLine(): bool {
		return match ($this) {
			self::G, self::C, self::F => true,
			self::PERCUSSION, self::TAB => false,
		};
	}
}
