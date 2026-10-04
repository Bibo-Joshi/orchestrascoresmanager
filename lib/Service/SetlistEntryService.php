<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Utility\SetlistValidationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

/**
 * Service for managing Setlist Entries.
 */
class SetlistEntryService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly SetlistEntryMapper $setlistEntryMapper,
		private readonly SetlistService $setlistService,
		private readonly SetlistValidationHelper $validationHelper,
		private readonly AuthorizationService $authorizationService,
		private readonly SetlistPolicy $setlistPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get a setlist entry by its ID.
	 *
	 * @param int $id
	 * @return array Entry data
	 * @throws InvalidArgumentException
	 */
	public function getSetlistEntryById(int $id): array {
		$entry = $this->findSetlistEntryEntity($id);
		// Authorize against the parent setlist
		$setlist = $this->setlistService->findSetlistEntity($entry->getSetlistId());
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);
		return $entry->jsonSerialize();
	}

	/**
	 * Get the raw SetlistEntry entity by ID.
	 *
	 * @param int $id
	 * @return SetlistEntry
	 * @throws InvalidArgumentException
	 */
	public function findSetlistEntryEntity(int $id): SetlistEntry {
		try {
			return $this->setlistEntryMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new InvalidArgumentException($this->l->t('Setlist entry not found'));
		}
	}

	/**
	 * Update a setlist entry.
	 *
	 * @param int $id
	 * @param SetlistEntry $entry
	 * @return array Updated entry data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function updateSetlistEntry(int $id, SetlistEntry $entry): array {
		try {
			$existing = $this->setlistEntryMapper->find($id);
			// Authorize against the parent setlist
			$setlist = $this->setlistService->findSetlistEntity($existing->getSetlistId());
			$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

			// If changing the scoreId and setlist has a folderCollectionVersionId, validate membership
			$newScoreId = $entry->getScoreId();
			if ($newScoreId !== null && $newScoreId !== $existing->getScoreId()) {
				$versionId = $setlist->getFolderCollectionVersionId();
				if ($versionId !== null) {
					$this->validationHelper->validateScoreInFolderCollectionVersion(
						$newScoreId,
						$versionId
					);
				}
			}

			$entry->setId($id);
			$entry->setSetlistId($existing->getSetlistId());
			$updated = $this->setlistEntryMapper->update($entry);
			return $updated->jsonSerialize();
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new InvalidArgumentException($this->l->t('Setlist entry not found'));
		}
	}

	/**
	 * Delete a setlist entry.
	 *
	 * @param int $id
	 * @return void
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function deleteSetlistEntry(int $id): void {
		try {
			$entry = $this->setlistEntryMapper->find($id);
			// Authorize against the parent setlist
			$setlist = $this->setlistService->findSetlistEntity($entry->getSetlistId());
			$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

			$this->setlistEntryMapper->delete($entry);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new InvalidArgumentException($this->l->t('Setlist entry not found'));
		}
	}

	/**
	 * Batch update multiple setlist entries.
	 * Updates are applied in a transaction - if any update fails, none are applied.
	 *
	 * @param array<array{id: int, index?: int, comment?: ?string, moderationDuration?: ?int, breakDuration?: ?int, scoreId?: ?int}> $entries
	 * @return array[] Updated entries data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function batchUpdateSetlistEntries(array $entries): array {
		if (empty($entries)) {
			return [];
		}

		// Validate all entries first
		$entitiesById = [];
		foreach ($entries as $entryData) {
			if (!isset($entryData['id'])) {
				throw new InvalidArgumentException($this->l->t('Each entry must have an id field'));
			}
			try {
				$existing = $this->setlistEntryMapper->find($entryData['id']);
				$entitiesById[$entryData['id']] = $existing;
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				throw new InvalidArgumentException($this->l->t('Setlist entry with id %d not found', [$entryData['id']]));
			}
		}

		// Authorize each entry against its parent setlist
		$setlistsById = [];
		foreach ($entitiesById as $existing) {
			$setlistId = $existing->getSetlistId();
			$setlist = $this->setlistService->findSetlistEntity($setlistId);
			$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);
			// Cache setlist for validation phase
			$setlistsById[$setlistId] = $setlist;
		}

		// Prepare updated entities
		$updatedEntities = [];
		foreach ($entries as $entryData) {
			$existing = $entitiesById[$entryData['id']];

			// Update fields if provided
			if (isset($entryData['index'])) {
				$existing->setIndex($entryData['index']);
			}
			if (isset($entryData['comment'])) {
				$existing->setComment($entryData['comment']);
			}
			if (isset($entryData['moderationDuration'])) {
				$existing->setModerationDuration($entryData['moderationDuration']);
			}
			if (isset($entryData['breakDuration'])) {
				$existing->setBreakDuration($entryData['breakDuration']);
			}
			if (isset($entryData['scoreId'])) {
				// If changing the scoreId and setlist has a folderCollectionVersionId, validate membership
				$setlist = $setlistsById[$existing->getSetlistId()];
				$scoreId = $entryData['scoreId'];
				$versionId = $setlist->getFolderCollectionVersionId();
				/** @psalm-suppress RedundantConditionGivenDocblockType - scoreId can be null per docblock */
				if ($versionId !== null && $scoreId !== null) {
					$this->validationHelper->validateScoreInFolderCollectionVersion(
						$scoreId,
						$versionId
					);
				}
				$existing->setScoreId($scoreId);
			}

			$updatedEntities[] = $existing;
		}

		// Apply updates in a transaction via mapper
		$result = $this->setlistEntryMapper->batchUpdate($updatedEntities);
		return array_map(fn ($entry) => $entry->jsonSerialize(), $result);
	}
}
