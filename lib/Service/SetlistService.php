<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistPolicy;
use OCA\OrchestraScoresManager\Utility\SetlistValidationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IUserSession;

/**
 * Service for managing Setlists and their entries.
 */
class SetlistService {
	private const DEFAULT_PAGE_LIMIT = 50;
	private const MAX_PAGE_LIMIT = 500;
	private const SORT_FIELDS = ['creation', 'date', 'name'];

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly SetlistMapper $setlistMapper,
		private readonly SetlistEntryMapper $setlistEntryMapper,
		private readonly SetlistValidationHelper $validationHelper,
		private readonly AuthorizationService $authorizationService,
		private readonly SetlistPolicy $setlistPolicy,
		private readonly UserSettingsService $userSettingsService,
		private readonly FolderCollectionService $folderCollectionService,
		private readonly IUserSession $userSession,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Return setlists filtered by date, draft status, and/or published status.
	 * Only returns setlists that are allowed to be read based on the policy.
	 *
	 * @param string $filter Filter: 'all', 'future', or 'past'
	 * @param bool|null $isDraft Filter by draft status
	 * @param bool|null $isPublished Filter by published status
	 * @return array[] Array of setlist data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function getSetlists(string $filter = 'all', ?bool $isDraft = null, ?bool $isPublished = null): array {
		$setlists = match ($filter) {
			'all' => $this->setlistMapper->findAll($isDraft, $isPublished),
			'future' => $this->setlistMapper->findFuture($isDraft, $isPublished),
			'past' => $this->setlistMapper->findPast($isDraft, $isPublished),
			default => throw new InvalidArgumentException($this->l->t('Invalid filter parameter. Must be "all", "future", or "past".')),
		};

		// Filter based on policy - only return setlists user is allowed to read
		$allowedSetlists = [];
		foreach ($setlists as $setlist) {
			if ($this->setlistPolicy->allows(PolicyInterface::ACTION_READ, $setlist)) {
				$allowedSetlists[] = $setlist;
			}
		}

		return array_map(fn ($setlist) => $this->serializeSetlist($setlist), $allowedSetlists);
	}

	/**
	 * Get a page of the setlists containing a score.
	 * Only setlists the current user may read are returned and counted.
	 *
	 * If a cursor is passed, it carries the limit and sorting parameters. Explicitly passed
	 * parameters must match the encoded ones.
	 *
	 * @param int $scoreId
	 * @param int|null $limit Page size (default 50, max 500)
	 * @param string|null $cursor Opaque cursor returned by a previous call
	 * @param string|null $sortBy One of 'creation', 'date' (default), 'name'
	 * @param string|null $sortOrder 'ASC' or 'DESC' (default), case-insensitive
	 * @return array{setlists: list<array>, pagination: array{nextCursor: string|null, total: int}}
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function getSetlistsForScore(int $scoreId, ?int $limit = null, ?string $cursor = null, ?string $sortBy = null, ?string $sortOrder = null): array {
		$sortOrder = $sortOrder === null ? null : strtoupper($sortOrder);
		$offset = 0;

		if ($cursor !== null) {
			$decoded = $this->decodeCursor($cursor);
			foreach ([
				'limit' => [$limit, $decoded['limit']],
				'sortBy' => [$sortBy, $decoded['sortBy']],
				'sortOrder' => [$sortOrder, $decoded['sortOrder']],
			] as $name => [$explicit, $encoded]) {
				if ($explicit !== null && $explicit !== $encoded) {
					throw new InvalidArgumentException($this->l->t('The parameter "%s" does not match the cursor.', [$name]));
				}
			}
			$limit = $decoded['limit'];
			$sortBy = $decoded['sortBy'];
			$sortOrder = $decoded['sortOrder'];
			$offset = $decoded['offset'];
		}

		$limit ??= self::DEFAULT_PAGE_LIMIT;
		$sortBy ??= 'date';
		$sortOrder ??= 'DESC';

		if ($limit < 1 || $limit > self::MAX_PAGE_LIMIT) {
			throw new InvalidArgumentException($this->l->t('Invalid limit parameter. Must be between 1 and %d.', [self::MAX_PAGE_LIMIT]));
		}
		if (!in_array($sortBy, self::SORT_FIELDS, true)) {
			throw new InvalidArgumentException($this->l->t('Invalid sortBy parameter. Must be "creation", "date", or "name".'));
		}
		if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
			throw new InvalidArgumentException($this->l->t('Invalid sortOrder parameter. Must be "ASC" or "DESC".'));
		}

		// Drafts/unpublished setlists are only readable by editors, see SetlistPolicy
		$unpublishedProbe = new Setlist();
		$unpublishedProbe->setIsPublished(false);
		$publishedOnly = !$this->setlistPolicy->allows(PolicyInterface::ACTION_READ, $unpublishedProbe);

		// fetch one extra row to find out whether there is a next page
		$setlists = $this->setlistMapper->findByScoreId($scoreId, $publishedOnly, $sortBy, $sortOrder, $limit + 1, $offset);
		$hasMore = count($setlists) > $limit;
		$setlists = array_slice($setlists, 0, $limit);

		return [
			'setlists' => array_values(array_map(fn (Setlist $setlist) => $this->serializeSetlist($setlist), $setlists)),
			'pagination' => [
				'nextCursor' => $hasMore ? $this->encodeCursor($limit, $sortBy, $sortOrder, $offset + $limit) : null,
				'total' => $this->setlistMapper->countByScoreId($scoreId, $publishedOnly),
			],
		];
	}

	/**
	 * @param int $limit
	 * @param string $sortBy
	 * @param string $sortOrder
	 * @param int $offset
	 * @return string URL-safe cursor
	 */
	private function encodeCursor(int $limit, string $sortBy, string $sortOrder, int $offset): string {
		$json = json_encode(['l' => $limit, 's' => $sortBy, 'o' => $sortOrder, 'p' => $offset], JSON_THROW_ON_ERROR);
		return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
	}

	/**
	 * @param string $cursor
	 * @return array{limit: int, sortBy: string, sortOrder: string, offset: int}
	 * @throws InvalidArgumentException If the cursor is malformed
	 */
	private function decodeCursor(string $cursor): array {
		$json = base64_decode(strtr($cursor, '-_', '+/'), true);
		$data = $json === false ? null : json_decode($json, true);
		if (!is_array($data)
			|| !isset($data['l'], $data['s'], $data['o'], $data['p'])
			|| !is_int($data['l']) || !is_string($data['s']) || !is_string($data['o']) || !is_int($data['p'])
			|| $data['p'] < 0
		) {
			throw new InvalidArgumentException($this->l->t('Invalid cursor.'));
		}
		return ['limit' => $data['l'], 'sortBy' => $data['s'], 'sortOrder' => $data['o'], 'offset' => $data['p']];
	}

	/**
	 * Get a serialized setlist by its ID.
	 * Intended to be used by Controllers.
	 *
	 * @param int $id
	 * @return array Setlist data
	 * @throws InvalidArgumentException
	 */
	public function getSetlistById(int $id): array {
		$setlist = $this->findSetlistEntity($id);
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);
		return $this->serializeSetlist($setlist);
	}

	/**
	 * Get the raw Setlist entity by ID.
	 *
	 * @param int $id
	 * @return Setlist
	 * @throws InvalidArgumentException
	 */
	public function findSetlistEntity(int $id): Setlist {
		try {
			return $this->setlistMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new InvalidArgumentException($this->l->t('Setlist not found'));
		}
	}

	/**
	 * Create a new setlist.
	 *
	 * @param Setlist $setlist
	 * @return array Created setlist data
	 * @throws Exception
	 */
	public function createSetlist(Setlist $setlist): array {
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_CREATE);

		$user = $this->userSession->getUser();
		$userId = $user?->getUID();

		if ($userId !== null && ($setlist->getDefaultModerationDuration() === null || $setlist->getFolderCollectionVersionId() === null)) {
			$settings = $this->userSettingsService->getSetlistSettings($userId);

			if ($setlist->getDefaultModerationDuration() === null) {
				$defaultModerationTime = $settings['defaultModerationTime'] ?? null;
				if (is_int($defaultModerationTime)) {
					$setlist->setDefaultModerationDuration($defaultModerationTime);
				}
			}

			if ($setlist->getFolderCollectionVersionId() === null) {
				$defaultFolderCollectionId = $settings['defaultFolderCollectionId'] ?? null;
				if (is_int($defaultFolderCollectionId)) {
					try {
						$folderCollection = $this->folderCollectionService->findFolderCollectionEntity($defaultFolderCollectionId);
						$activeVersionId = $folderCollection->getActiveVersionId();
						if ($activeVersionId !== null) {
							$setlist->setFolderCollectionVersionId($activeVersionId);
						}
					} catch (InvalidArgumentException) {
						// Ignore missing default folder collections.
					}
				}
			}
		}

		$created = $this->setlistMapper->insert($setlist);
		return $this->serializeSetlist($created);
	}

	/**
	 * Update an existing setlist.
	 * If the folderCollectionVersionId is being updated, validates that all existing
	 * scores in the setlist belong to the new folder collection version.
	 *
	 * @param Setlist $setlist
	 * @return array Updated setlist data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function updateSetlist(Setlist $setlist): array {
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		// Check if folderCollectionVersionId is being updated
		$existingSetlist = $this->findSetlistEntity($setlist->getId());
		$oldVersionId = $existingSetlist->getFolderCollectionVersionId();
		$newVersionId = $setlist->getFolderCollectionVersionId();

		if ($oldVersionId !== $newVersionId && $newVersionId !== null) {
			// Version is being updated - validate all existing scores belong to the new version
			$entries = $this->setlistEntryMapper->findBySetlistId($setlist->getId());
			foreach ($entries as $entry) {
				$scoreId = $entry->getScoreId();
				if ($scoreId !== null) {
					$this->validationHelper->validateScoreInFolderCollectionVersion(
						$scoreId,
						$newVersionId
					);
				}
			}
		}

		$updated = $this->setlistMapper->update($setlist);
		return $this->serializeSetlist($updated);
	}

	/**
	 * Delete a setlist.
	 *
	 * @param int $id
	 * @return void
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function deleteSetlist(int $id): void {
		$setlist = $this->findSetlistEntity($id);
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_DELETE, $setlist);
		$this->setlistMapper->delete($setlist);
		// Entries are deleted automatically via CASCADE foreign key constraint
	}

	/**
	 * Get all entries in a setlist.
	 *
	 * @param int $setlistId
	 * @return array[] Array of entry data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function getSetlistEntries(int $setlistId): array {
		$setlist = $this->findSetlistEntity($setlistId);
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_READ, $setlist);

		$entries = $this->setlistEntryMapper->findBySetlistId($setlistId);
		return array_map(fn ($entry) => $entry->jsonSerialize(), $entries);
	}

	/**
	 * Create a new entry in a setlist.
	 * Exactly one of scoreId or breakDuration must be set.
	 * If the setlist has a folderCollectionVersionId, the score must belong to that version.
	 *
	 * @param int $setlistId
	 * @param SetlistEntry $entry
	 * @return array Created entry data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function createSetlistEntry(int $setlistId, SetlistEntry $entry): array {
		$setlist = $this->findSetlistEntity($setlistId);
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_UPDATE, $setlist);

		// Validate that exactly one of scoreId or breakDuration is set
		$hasScoreId = $entry->getScoreId() !== null;
		$hasBreakDuration = $entry->getBreakDuration() !== null;

		if (!$hasScoreId && !$hasBreakDuration) {
			throw new InvalidArgumentException($this->l->t('Either scoreId or breakDuration must be set'));
		}
		if ($hasScoreId && $hasBreakDuration) {
			throw new InvalidArgumentException($this->l->t('Only one of scoreId or breakDuration can be set'));
		}

		// If setlist has a folderCollectionVersionId and the entry is a score, validate membership
		$scoreId = $entry->getScoreId();
		$versionId = $setlist->getFolderCollectionVersionId();
		if ($hasScoreId && $scoreId !== null && $versionId !== null) {
			$this->validationHelper->validateScoreInFolderCollectionVersion(
				$scoreId,
				$versionId
			);
		}

		$entry->setSetlistId($setlistId);
		try {
			$created = $this->setlistEntryMapper->insert($entry);
			return $created->jsonSerialize();
		} catch (Exception $e) {
			// Check if this is a unique constraint violation for the index
			if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw new InvalidArgumentException($this->l->t('An entry with this index already exists in the setlist'));
			}
			throw $e;
		}
	}

	/**
	 * Clone an existing setlist with a new title.
	 * Copies description, startDateTime, duration, defaultModerationDuration, and
	 * folderCollectionVersionId from the source setlist, but sets isDraft=true and
	 * isPublished=false. Does not copy setlist entries.
	 *
	 * @param int $id The ID of the setlist to clone
	 * @param string $title The title for the cloned setlist
	 * @return array Created setlist data
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function cloneSetlist(int $id, string $title): array {
		$source = $this->findSetlistEntity($id);
		$this->authorizationService->authorizePolicy($this->setlistPolicy, PolicyInterface::ACTION_CREATE);

		$clone = new Setlist();
		$clone->setTitle($title);
		$clone->setDescription($source->getDescription());
		$startDateTime = $source->getStartDateTime();
		if ($startDateTime !== null) {
			$clone->setStartDateTime($startDateTime);
		}
		$duration = $source->getDuration();
		if ($duration !== null) {
			$clone->setDuration($duration);
		}
		$clone->setDefaultModerationDuration($source->getDefaultModerationDuration());
		$clone->setFolderCollectionVersionId($source->getFolderCollectionVersionId());
		$clone->setIsDraft(true);
		$clone->setIsPublished(false);

		$created = $this->setlistMapper->insert($clone);

		// Copy all entries from the source setlist
		$sourceEntries = $this->setlistEntryMapper->findBySetlistId($id);
		foreach ($sourceEntries as $sourceEntry) {
			$entryClone = new SetlistEntry();
			$entryClone->setSetlistId($created->getId());
			$entryClone->setIndex($sourceEntry->getIndex());
			$entryClone->setComment($sourceEntry->getComment());
			$entryClone->setModerationDuration($sourceEntry->getModerationDuration());
			$entryClone->setBreakDuration($sourceEntry->getBreakDuration());
			$entryClone->setScoreId($sourceEntry->getScoreId());
			$this->setlistEntryMapper->insert($entryClone);
		}

		return $this->serializeSetlist($created);
	}

	/**
	 * Serialize a setlist entity to an array.
	 *
	 * @param Setlist $setlist
	 * @return array
	 */
	private function serializeSetlist(Setlist $setlist): array {
		return method_exists($setlist, 'jsonSerialize') ? $setlist->jsonSerialize() : (array)$setlist;
	}
}
