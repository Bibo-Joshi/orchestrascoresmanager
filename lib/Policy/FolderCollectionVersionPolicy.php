<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Service\PermissionService;

/**
 * Policy for FolderCollectionVersion entity.
 *
 * Enforces the following constraints:
 * - Non-active versions (valid_to is not null) cannot be edited by anyone
 * - Creating a version is allowed only if the valid_from/to range doesn't overlap any existing version
 * - For the active version, only the valid_to attribute can be set to a date, making it inactive
 */
class FolderCollectionVersionPolicy implements PolicyInterface {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly PermissionService $permissionService,
	) {
	}

	public function allows(string $action, ?object $subject = null): bool {
		return match ($action) {
			self::ACTION_CREATE => $this->permissionService->canCurrentUserEdit(),
			self::ACTION_UPDATE => $this->canUpdate($subject),
			self::ACTION_DELETE => $this->permissionService->canCurrentUserEdit(),
			self::ACTION_READ => true,
			default => false,
		};
	}

	/**
	 * Check if the version can be updated.
	 *
	 * @param object|null $subject The FolderCollectionVersion entity
	 * @return bool
	 */
	private function canUpdate(?object $subject): bool {
		if (!$this->permissionService->canCurrentUserEdit()) {
			return false;
		}

		// If no subject provided, deny (we need the version to check its status)
		if ($subject === null || !($subject instanceof FolderCollectionVersion)) {
			return false;
		}

		// Non-active versions (valid_to is not null) cannot be edited
		if ($subject->getValidTo() !== null) {
			return false;
		}

		return true;
	}
}
