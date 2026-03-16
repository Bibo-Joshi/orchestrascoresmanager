<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCP\AppFramework\Services\IAppConfig;

class ConfigService {
	private IAppConfig $appConfig;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		IAppConfig $appConfig,
	) {
		$this->appConfig = $appConfig;
	}

	/**
	 * Get the list of groups allowed to edit the table
	 *
	 * @return list<string> List of group IDs
	 */
	public function getAllowedEditGroups(): array {
		/** @var list<string> */
		return $this->appConfig->getAppValueArray('allowed_edit_groups');
	}

	/**
	 * Set the list of groups allowed to edit the table
	 *
	 * @param array $groupIds List of group IDs
	 */
	public function setAllowedEditGroups(array $groupIds): void {
		// Remove empty values and duplicates, then reset array indices
		$groupIds = array_values(array_filter(array_unique($groupIds)));
		$this->appConfig->setAppValueArray('allowed_edit_groups', $groupIds);
	}

	/**
	 * Check if a specific group is allowed to edit
	 *
	 * @param string $groupId Group ID to check
	 * @return bool True if the group is allowed to edit
	 * @psalm-suppress PossiblyUnusedMethod - May be used in future
	 */
	public function isGroupAllowedToEdit(string $groupId): bool {
		$allowedGroups = $this->getAllowedEditGroups();
		return in_array($groupId, $allowedGroups, true);
	}


}
