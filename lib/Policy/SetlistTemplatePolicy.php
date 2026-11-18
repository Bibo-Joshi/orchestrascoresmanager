<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

use OCA\OrchestraScoresManager\Service\PermissionService;

/** Shared templates may be read by all OSM users and changed by editors. */
class SetlistTemplatePolicy implements PolicyInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Nextcloud DI constructs policies */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function allows(string $action, ?object $subject = null): bool {
		return match ($action) {
			self::ACTION_READ => true,
			self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE => $this->permissionService->canCurrentUserEdit(),
			default => false,
		};
	}
}
