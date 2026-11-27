<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db\Enum;

/**
 * Supported types of conductor's score variants.
 *
 * The type is stored as a plain string in the database and validated by ConductorsScoreVariantService.
 */
enum ConductorsScoreVariantType: string {
	case FULL = 'full';
	case CONDENSED = 'condensed';
}
