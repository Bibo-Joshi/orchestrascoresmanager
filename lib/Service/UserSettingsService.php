<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCP\Config\IUserConfig;

/**
 * Service for managing per-user application settings.
 *
 * Settings are stored lazily via NextCloud's IUserConfig.
 */
class UserSettingsService {
	private const KEY_DEFAULT_MODERATION_TIME = 'setlists_default_moderation_time';
	private const KEY_DEFAULT_FOLDER_COLLECTION_ID = 'setlists_default_folder_collection_id';

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly IUserConfig $userConfig,
		private readonly string $appName,
	) {
	}

	/**
	 * Return the setlist settings for the given user.
	 *
	 * @param string $userId The user whose settings to load.
	 * @return array{defaultModerationTime: string|null, defaultFolderCollectionId: int|null}
	 */
	public function getSetlistSettings(string $userId): array {
		$moderationTime = $this->userConfig->getValueInt(
			$userId,
			$this->appName,
			self::KEY_DEFAULT_MODERATION_TIME,
			-1,
			lazy: true,
		);

		$folderCollectionIdRaw = $this->userConfig->getValueInt(
			$userId,
			$this->appName,
			self::KEY_DEFAULT_FOLDER_COLLECTION_ID,
			-1,
			lazy: true,
		);

		return [
			'defaultModerationTime' => $moderationTime === -1 ? null : $moderationTime,
			'defaultFolderCollectionId' => $folderCollectionIdRaw === -1 ? null : $folderCollectionIdRaw,
		];
	}

	/**
	 * Persist setlist settings for the given user.
	 *
	 * @param string $userId The user whose settings to update.
	 * @param int|null $defaultModerationTime Time string (e.g. 60 seconds) or null to clear.
	 * @param int|null $defaultFolderCollectionId Folder collection ID or null to clear.
	 */
	public function updateSetlistSettings(
		string $userId,
		?int $defaultModerationTime,
		?int $defaultFolderCollectionId,
	): void {
		if ($defaultModerationTime !== null) {
			$this->userConfig->setValueInt(
				$userId,
				$this->appName,
				self::KEY_DEFAULT_MODERATION_TIME,
				$defaultModerationTime,
				true,
			);
		} else {
			$this->userConfig->deleteUserConfig(
				$userId,
				$this->appName,
				self::KEY_DEFAULT_MODERATION_TIME,
			);
		}

		if ($defaultFolderCollectionId !== null) {
			$this->userConfig->setValueInt(
				$userId,
				$this->appName,
				self::KEY_DEFAULT_FOLDER_COLLECTION_ID,
				$defaultFolderCollectionId,
				true,
			);
		} else {
			$this->userConfig->deleteUserConfig(
				$userId,
				$this->appName,
				self::KEY_DEFAULT_FOLDER_COLLECTION_ID,
			);
		}
	}
}
