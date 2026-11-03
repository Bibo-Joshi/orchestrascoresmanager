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
	private const KEY_PDF_LAYOUTS = 'pdf_layout_profiles_v1';

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
	 * @return array{defaultModerationTime: int|null, defaultFolderCollectionId: int|null}
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
	/** Each user's PDF profiles are stored as a versioned JSON preference. */
	public function getPdfLayouts(string $userId): ?array {
		$json = $this->userConfig->getValueString($userId, $this->appName, self::KEY_PDF_LAYOUTS, '__unconfigured__', lazy: true);
		if ($json === '__unconfigured__') {
			return null;
		}
		$data = json_decode($json, true);
		return is_array($data) && array_is_list($data) ? $data : [];
	}

	/** Limit storage size and validate the shape before persisting user input. */
	public function putPdfLayouts(string $userId, array $profiles): array {
		if (!array_is_list($profiles) || count($profiles) > 12) {
			throw new \InvalidArgumentException('Too many PDF layouts or invalid structure');
		}
		$ids = [];
		foreach ($profiles as $profile) {
			if (!is_array($profile) || !isset($profile['id'], $profile['name'], $profile['rules'])
				|| !is_string($profile['id']) || $profile['id'] === 'standard'
				|| strlen($profile['id']) > 64 || $profile['id'] === ''
				|| in_array($profile['id'], $ids, true)
				|| !is_string($profile['name']) || trim($profile['name']) === '' || strlen($profile['name']) > 160
				|| !is_array($profile['rules']) || !array_is_list($profile['rules']) || count($profile['rules']) > 30) {
				throw new \InvalidArgumentException('Invalid PDF layout');
			}
			$ids[] = $profile['id'];
		}
		$json = json_encode($profiles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		if (strlen($json) > 45000) {
			throw new \InvalidArgumentException('PDF layout configuration is too large');
		}
		$this->userConfig->setValueString($userId, $this->appName, self::KEY_PDF_LAYOUTS, $json, true);
		return $profiles;
	}

}
