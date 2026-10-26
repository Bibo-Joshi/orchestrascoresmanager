<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

class ScoreAudioPolicy implements PolicyInterface {
	public function allows(string $action, ?object $subject = null): bool {
		// Audio examples are a community effort: all actions are allowed for every user, independent of edit rights
		return match ($action) {
			self::ACTION_CREATE, self::ACTION_READ, self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_SHARE => true,
			// default deny
			default => false,
		};
	}
}
