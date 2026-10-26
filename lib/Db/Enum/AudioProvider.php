<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db\Enum;

/**
 * Supported providers for score audio examples.
 *
 * The provider is stored as a plain string in the database, so adding a provider only requires a new case here
 * and the matching URL/icon mapping in the frontend - no schema change.
 */
enum AudioProvider: string {
	case YOUTUBE = 'youtube';
	case SPOTIFY = 'spotify';
	case APPLE_MUSIC = 'apple-music';
	case DEEZER = 'deezer';
	case AMAZON_MUSIC = 'amazon-music';
}
