<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

use OCA\OrchestraScoresManager\Service\PermissionService;

/**
 * Policy for ScoreBook authorization.
 * Users with edit rights may write & delete, everyone can read.
 */
class ScoreBookPolicy implements PolicyInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	/**
	 * Determine if the given action is allowed.
	 *
	 * @param string $action
	 * @param object|null $subject
	 * @return bool
	 */
	public function allows(string $action, ?object $subject = null): bool {
		return match ($action) {
			self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_SHARE => $this->permissionService->canCurrentUserEdit(),
			// read is allowed for everyone
			self::ACTION_READ => true,
			// default deny
			default => false,
		};
	}
}
