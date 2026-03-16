<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

class PermissionService {
	private IGroupManager $groupManager;

	private IUserSession $userSession;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ConfigService $configService,
		IGroupManager $groupManager,
		IUserSession $userSession,
	) {
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
	}

	/**
	 * Check if the current user is allowed to edit the table
	 *
	 * @return bool True if the current user can edit
	 */
	public function canCurrentUserEdit(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		// Get groups the current user belongs to
		$userGroups = $this->groupManager->getUserGroups($user);

		// Get allowed groups from config
		$allowedGroups = $this->configService->getAllowedEditGroups();

		// If no groups are configured as allowed, deny access
		if ($allowedGroups === []) {
			return false;
		}

		// Check if any of the user's groups are in the allowed list
		foreach ($userGroups as $group) {
			if (in_array($group->getGID(), $allowedGroups, true)) {
				return true;
			}
		}

		return false;
	}
}
