<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

use OCA\OrchestraScoresManager\Service\PermissionService;

class ScorePolicy implements PolicyInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function allows(string $action, ?object $subject = null): bool {
		// For now all write-ish actions are based on canCurrentUserEdit
		return match ($action) {
			self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_SHARE => $this->permissionService->canCurrentUserEdit(),
			// read is allowed for everyone
			self::ACTION_READ => true,
			// default deny
			default => false,
		};
	}
}
