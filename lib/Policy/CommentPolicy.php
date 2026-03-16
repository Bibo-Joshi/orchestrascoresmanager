<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

use OCA\OrchestraScoresManager\Service\PermissionService;

class CommentPolicy implements PolicyInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function allows(string $action, ?object $subject = null): bool {
		// Read and write access to comments should be granted only if the user has edit rights
		return match ($action) {
			self::ACTION_CREATE, self::ACTION_READ, self::ACTION_DELETE => $this->permissionService->canCurrentUserEdit(),
			// default deny
			default => false,
		};
	}
}
