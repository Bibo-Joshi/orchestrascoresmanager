<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Policy\FolderCollectionVersionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Utility\DateTimeHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

class FolderCollectionVersionService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly FolderCollectionVersionMapper $versionMapper,
		private readonly FolderCollectionMapper $folderCollectionMapper,
		private readonly ScoreFolderCollectionLinkMapper $linkMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly FolderCollectionVersionPolicy $versionPolicy,
		private IL10N $l,
	) {
	}

	/**
	 * Get a version by its ID.
	 *
	 * @param int $id
	 * @return array Version data
	 * @throws \InvalidArgumentException
	 */
	public function getVersionById(int $id): array {
		$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_READ);

		try {
			$version = $this->versionMapper->find($id);
			return $version->jsonSerialize();
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}
	}

	/**
	 * Get the raw FolderCollectionVersion entity by ID.
	 *
	 * @param int $id
	 * @return FolderCollectionVersion
	 * @throws \InvalidArgumentException
	 * @psalm-suppress PossiblyUnusedMethod - May be called by controllers
	 */
	public function findVersionEntity(int $id): FolderCollectionVersion {
		try {
			return $this->versionMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}
	}

	/**
	 * Get all versions for a folder collection.
	 *
	 * @param int $folderCollectionId
	 * @return array[] Array of version data
	 * @throws \InvalidArgumentException
	 */
	public function getVersionsForFolderCollection(int $folderCollectionId): array {
		$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_READ);

		// Verify folder collection exists
		try {
			$this->folderCollectionMapper->find($folderCollectionId);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		$versions = $this->versionMapper->findAllForFolderCollection($folderCollectionId);
		return array_map(fn (FolderCollectionVersion $v) => $v->jsonSerialize(), $versions);
	}

	/**
	 * Create a new version for a folder collection.
	 * The new version becomes the active version.
	 * Optionally copies all scores/scorebooks from an existing version.
	 *
	 * @param int $folderCollectionId
	 * @param string $validFrom Date string in Y-m-d format
	 * @param string|null $validTo Date string in Y-m-d format or null for active version
	 * @param int|null $copyFromVersionId Version ID to copy scores/scorebooks from
	 * @return array The created version data
	 * @throws \InvalidArgumentException
	 */
	public function createVersion(
		int $folderCollectionId,
		string $validFrom,
		?string $validTo = null,
		?int $copyFromVersionId = null,
	): array {
		$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_CREATE);

		// Verify folder collection exists
		try {
			$folderCollection = $this->folderCollectionMapper->find($folderCollectionId);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		// Parse and validate dates using helper
		$validFromDate = DateTimeHelper::parseDate($validFrom);
		$validToDate = $validTo !== null ? DateTimeHelper::parseDate($validTo) : null;
		DateTimeHelper::validateDateRange($validFromDate, $validToDate);

		// Check for overlapping versions
		if ($this->versionMapper->hasOverlappingVersion($folderCollectionId, $validFromDate, $validToDate)) {
			throw new \InvalidArgumentException($this->l->t('Version date range overlaps with an existing version'));
		}

		// At most one active version (validTo = null) can exist
		if ($validTo === null) {
			$existingActive = $this->versionMapper->findActiveVersion($folderCollectionId);
			if ($existingActive !== null) {
				throw new \InvalidArgumentException($this->l->t('An active version already exists. Deactivate it first by setting valid_to.'));
			}
		}

		// Create the version with DateTime objects
		$version = new FolderCollectionVersion();
		$version->setFolderCollectionId($folderCollectionId);
		$version->setValidFrom($validFromDate);
		$version->setValidTo($validToDate);

		$created = $this->versionMapper->insert($version);

		// Copy links from source version if specified
		if ($copyFromVersionId !== null) {
			try {
				$this->versionMapper->find($copyFromVersionId);
				$this->linkMapper->copyLinksToVersion($copyFromVersionId, $created->getId());
			} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception) {
				// Source version doesn't exist, skip copying
			}
		}

		// If this is the active version, update folder collection's active_version_id
		if ($validTo === null) {
			$folderCollection->setActiveVersionId($created->getId());
			$this->folderCollectionMapper->update($folderCollection);
		}

		return $created->jsonSerialize();
	}

	/**
	 * Create a new version based on the current active version.
	 * Sets the current active version's valid_to to one day before the new version starts,
	 * and creates a new active version starting on the specified date.
	 *
	 * @param int $folderCollectionId
	 * @param string|null $validFrom Date string in Y-m-d format for new version start date (defaults to today)
	 * @return array The created version data
	 * @throws \InvalidArgumentException
	 */
	public function startNewVersion(int $folderCollectionId, ?string $validFrom = null): array {
		$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_CREATE);

		// Verify folder collection exists
		try {
			$folderCollection = $this->folderCollectionMapper->find($folderCollectionId);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		// Parse and validate the new version start date
		$newValidFrom = $validFrom !== null ? DateTimeHelper::parseDate($validFrom) : DateTimeHelper::today();

		// Find current active version
		$activeVersion = $this->versionMapper->findActiveVersion($folderCollectionId);
		$copyFromVersionId = null;

		if ($activeVersion !== null) {
			$activeValidFrom = $activeVersion->getValidFrom();

			// Validate: new version must start at least 1 day after current version started
			if ($newValidFrom <= $activeValidFrom) {
				throw new \InvalidArgumentException($this->l->t('New version must start at least one day after the current version'));
			}

			// Calculate valid_to for current version: one day before new version starts
			$newValidTo = $newValidFrom->modify('-1 day');

			// Validate: valid_to must be >= valid_from
			DateTimeHelper::validateDateRange($activeValidFrom, $newValidTo);

			// Deactivate current version by setting valid_to
			$activeVersion->setValidTo($newValidTo);
			$this->versionMapper->update($activeVersion);
			$copyFromVersionId = $activeVersion->getId();
		}

		// Create new active version starting on the specified date
		$newVersion = new FolderCollectionVersion();
		$newVersion->setFolderCollectionId($folderCollectionId);
		$newVersion->setValidFrom($newValidFrom);
		$newVersion->setValidTo(null);

		$created = $this->versionMapper->insert($newVersion);

		// Copy links from previous active version if it existed
		if ($copyFromVersionId !== null) {
			$this->linkMapper->copyLinksToVersion($copyFromVersionId, $created->getId());
		}

		// Update folder collection's active_version_id
		$folderCollection->setActiveVersionId($created->getId());
		$this->folderCollectionMapper->update($folderCollection);

		return $created->jsonSerialize();
	}

	/**
	 * Update a version (only valid_to can be set for active versions).
	 *
	 * @param int $id
	 * @param string|null $validTo Date string in Y-m-d format or null
	 * @return array The updated version data
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function updateVersion(int $id, ?string $validTo): array {
		try {
			$version = $this->versionMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}

		// Check policy (includes check that version is active)
		$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_UPDATE, $version);

		// Validate the date
		$validToDate = null;
		if ($validTo !== null) {
			$validToDate = DateTimeHelper::parseDate($validTo);

			// valid_to should be >= valid_from
			$validFromDate = $version->getValidFrom();
			DateTimeHelper::validateDateRange($validFromDate, $validToDate);

			// Check for overlap with other versions
			$folderCollectionId = $version->getFolderCollectionId();
			if ($this->versionMapper->hasOverlappingVersion($folderCollectionId, $validFromDate, $validToDate, $id)) {
				throw new \InvalidArgumentException($this->l->t('Version date range overlaps with an existing version'));
			}
		}

		$version->setValidTo($validToDate);
		$updated = $this->versionMapper->update($version);

		// If the version is being deactivated (validTo is now set), clear active_version_id on folder collection
		if ($validTo !== null) {
			$folderCollection = $this->folderCollectionMapper->find($version->getFolderCollectionId());
			if ($folderCollection->getActiveVersionId() === $id) {
				$folderCollection->setActiveVersionId(null);
				$this->folderCollectionMapper->update($folderCollection);
			}
		}

		return $updated->jsonSerialize();
	}

	/**
	 * Get the active version ID for a folder collection.
	 *
	 * @param int $folderCollectionId
	 * @return int|null The active version ID or null if no active version exists
	 * @throws \InvalidArgumentException
	 * @psalm-suppress PossiblyUnusedMethod - Public API for potential use
	 */
	public function getActiveVersionId(int $folderCollectionId): ?int {
		try {
			$folderCollection = $this->folderCollectionMapper->find($folderCollectionId);
			return $folderCollection->getActiveVersionId();
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}
	}

	/**
	 * Get the latest version ID for a folder collection (regardless of active status).
	 *
	 * @param int $folderCollectionId
	 * @return int The latest version ID
	 * @throws \InvalidArgumentException
	 * @throws DoesNotExistException if no version exists
	 * @psalm-suppress PossiblyUnusedMethod - Public API for potential use
	 */
	public function getLatestVersionId(int $folderCollectionId): int {
		try {
			$this->folderCollectionMapper->find($folderCollectionId);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		$version = $this->versionMapper->findLatestVersion($folderCollectionId);
		return $version->getId();
	}

	/**
	 * Check if a version is active.
	 *
	 * @param int $versionId
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @psalm-suppress PossiblyUnusedMethod - Public API for potential use
	 */
	public function isVersionActive(int $versionId): bool {
		try {
			$version = $this->versionMapper->find($versionId);
			return $version->getValidTo() === null;
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}
	}
}
