<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\FolderCollectionPolicy;
use OCA\OrchestraScoresManager\Policy\FolderCollectionVersionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Utility\DateTimeHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

class FolderCollectionService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly FolderCollectionMapper $folderCollectionMapper,
		private readonly FolderCollectionVersionMapper $versionMapper,
		private readonly ScoreFolderCollectionLinkMapper $linkMapper,
		private readonly ScoreMapper $scoreMapper,
		private readonly ScoreBookMapper $scoreBookMapper,
		private readonly ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly FolderCollectionPolicy $folderCollectionPolicy,
		private readonly FolderCollectionVersionPolicy $versionPolicy,
		private IL10N $l,
	) {
	}

	/**
	 * Get a folder collection and its required active version ID.
	 * Throws if the folder collection doesn't exist or has no active version.
	 *
	 * @param int $folderCollectionId
	 * @return array{folderCollection: FolderCollection, activeVersionId: int}
	 * @throws \InvalidArgumentException
	 */
	private function getFolderCollectionWithActiveVersion(int $folderCollectionId): array {
		try {
			$folderCollection = $this->folderCollectionMapper->find($folderCollectionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		$activeVersionId = $folderCollection->getActiveVersionId();
		if ($activeVersionId === null) {
			throw new \InvalidArgumentException($this->l->t('No active version exists for this folder collection'));
		}

		return ['folderCollection' => $folderCollection, 'activeVersionId' => $activeVersionId];
	}

	/**
	 * Resolve and validate a version ID for a folder collection.
	 * Returns the effective version ID to use, validating it belongs to the folder collection.
	 *
	 * @param FolderCollection $folderCollection
	 * @param int|null $versionId Optional version ID, uses active/latest if null
	 * @return int|null The effective version ID, or null if no version exists
	 * @throws \InvalidArgumentException
	 */
	private function resolveVersionId(FolderCollection $folderCollection, ?int $versionId): ?int {
		$folderCollectionId = $folderCollection->getId();
		$effectiveVersionId = $versionId ?? $folderCollection->getActiveVersionId();

		if ($effectiveVersionId === null) {
			// Try to get latest version if no active version
			try {
				$latestVersion = $this->versionMapper->findLatestVersion($folderCollectionId);
				$effectiveVersionId = $latestVersion->getId();
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				return null;
			}
		}

		// Verify the version belongs to this folder collection
		try {
			$version = $this->versionMapper->find($effectiveVersionId);
			if ($version->getFolderCollectionId() !== $folderCollectionId) {
				throw new \InvalidArgumentException($this->l->t('Version does not belong to this folder collection'));
			}
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}

		return $effectiveVersionId;
	}

	/**
	 * Get a folder collection by its ID with its score count.
	 * Score count uses the latest version (active or not).
	 *
	 * @param int $id
	 * @return array Folder collection data with scoreCount
	 * @throws \Exception
	 */
	public function getFolderCollectionById(int $id): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		$folderCollection = $this->findFolderCollectionEntity($id);
		$fcData = method_exists($folderCollection, 'jsonSerialize') ? $folderCollection->jsonSerialize() : (array)$folderCollection;

		// Get the latest version for score count (folder collection must have a version)
		$latestVersion = $this->versionMapper->findLatestVersion($id);
		$fcData['scoreCount'] = $this->getTotalScoreCountForVersion($latestVersion->getId());

		return $fcData;
	}

	/**
	 * Get the raw FolderCollection entity by ID.
	 *
	 * @param int $id
	 * @return FolderCollection
	 * @throws \InvalidArgumentException
	 */
	public function findFolderCollectionEntity(int $id): FolderCollection {
		try {
			return $this->folderCollectionMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}
	}

	/**
	 * Get all folder collections with their score counts.
	 *
	 * @return array[] Array of folder collection data with scoreCount
	 * @throws \Exception
	 */
	public function getAllFolderCollections(): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);
		$folderCollections = $this->folderCollectionMapper->findAll();
		return $this->enrichWithScoreCounts($folderCollections);
	}

	/**
	 * Enrich folder collections with their score counts.
	 * Score count uses the latest version (active or not) for each folder collection.
	 *
	 * @param FolderCollection[] $folderCollections Array of folder collections
	 * @return array[] Array of folder collection data with scoreCount
	 */
	private function enrichWithScoreCounts(array $folderCollections): array {
		$result = [];
		foreach ($folderCollections as $folderCollection) {
			$fcData = method_exists($folderCollection, 'jsonSerialize') ? $folderCollection->jsonSerialize() : (array)$folderCollection;

			// Get the latest version for score count (folder collection must have a version)
			$latestVersion = $this->versionMapper->findLatestVersion($folderCollection->getId());
			$fcData['scoreCount'] = $this->getTotalScoreCountForVersion($latestVersion->getId());

			$result[] = $fcData;
		}
		return $result;
	}

	/**
	 * Create a new folder collection.
	 * Also creates an initial active version.
	 *
	 * @param FolderCollection $folderCollection
	 * @param string|null $validFrom Optional start date for initial version (Y-m-d format), defaults to today
	 * @return array Folder collection data with scoreCount
	 * @throws Exception
	 * @throws \Exception
	 */
	public function createFolderCollection(FolderCollection $folderCollection, ?string $validFrom = null): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_CREATE);

		// Validate collection type
		$allowedTypes = [FolderCollectionType::ALPHABETICAL->value, FolderCollectionType::INDEXED->value];
		if (!in_array($folderCollection->getCollectionType(), $allowedTypes, true)) {
			throw new \InvalidArgumentException($this->l->t('Invalid collection type. Must be "alphabetical" or "indexed".'));
		}

		$created = $this->folderCollectionMapper->insert($folderCollection);

		// Create initial active version
		if ($validFrom !== null) {
			// Validate and parse the provided date using helper
			$versionDate = DateTimeHelper::parseDate($validFrom);
		} else {
			// Default to today (normalized to midnight UTC)
			$versionDate = DateTimeHelper::today();
		}

		$version = new FolderCollectionVersion();
		$version->setFolderCollectionId($created->getId());
		$version->setValidFrom($versionDate);
		$version->setValidTo(null);
		$insertedVersion = $this->versionMapper->insert($version);

		// Update folder collection with active version
		$created->setActiveVersionId($insertedVersion->getId());
		$updated = $this->folderCollectionMapper->update($created);

		$fcData = method_exists($updated, 'jsonSerialize') ? $updated->jsonSerialize() : (array)$updated;
		$fcData['scoreCount'] = 0; // New folder collection has no scores
		return $fcData;
	}

	/**
	 * Update a folder collection.
	 * Note: Collection type cannot be changed after creation.
	 *
	 * @param FolderCollection $folderCollection
	 * @return array Folder collection data with scoreCount
	 * @throws Exception
	 * @throws \Exception
	 */
	public function updateFolderCollection(FolderCollection $folderCollection): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_UPDATE);

		// Prevent changing collection type
		try {
			$existing = $this->folderCollectionMapper->find($folderCollection->getId());
			if ($existing->getCollectionType() !== $folderCollection->getCollectionType()) {
				throw new \InvalidArgumentException($this->l->t('Collection type cannot be changed after creation.'));
			}
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		$updated = $this->folderCollectionMapper->update($folderCollection);
		$fcData = method_exists($updated, 'jsonSerialize') ? $updated->jsonSerialize() : (array)$updated;

		// Get the latest version for score count (folder collection must have a version)
		$latestVersion = $this->versionMapper->findLatestVersion($folderCollection->getId());
		$fcData['scoreCount'] = $this->getTotalScoreCountForVersion($latestVersion->getId());

		return $fcData;
	}

	/**
	 * Delete a folder collection.
	 *
	 * @param int $id
	 * @throws Exception
	 * @throws \Exception
	 */
	public function deleteFolderCollection(int $id): void {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_DELETE);

		try {
			$folderCollection = $this->folderCollectionMapper->find($id);
			$this->folderCollectionMapper->delete($folderCollection);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}
	}

	/**
	 * Get all scores in a folder collection version, including scores from score books.
	 * Direct scores will have scoreBook=null, scores from books will have scoreBook set
	 * with {id, index} from their book membership.
	 *
	 * @param int $folderCollectionId
	 * @param int|null $versionId Version ID, if null uses active version
	 * @return array Array of scores with index information
	 * @throws \Exception
	 */
	public function getScoresInFolderCollection(int $folderCollectionId, ?int $versionId = null): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		// Verify the folder collection exists
		$fc = $this->findFolderCollectionEntity($folderCollectionId);

		// Resolve and validate version ID
		$effectiveVersionId = $this->resolveVersionId($fc, $versionId);
		if ($effectiveVersionId === null) {
			return []; // No version exists
		}

		$collectionType = $fc->getCollectionType();
		$isIndexed = $collectionType === FolderCollectionType::INDEXED->value;

		$scores = [];

		// Get directly linked scores - use batch fetch for efficiency
		$directScoreInfos = $this->linkMapper->findScoresForVersion($effectiveVersionId);
		$directScoreIds = array_map(fn ($info) => $info['id'], $directScoreInfos);
		$directScores = $this->scoreMapper->findMultiple($directScoreIds);

		// Create a map for quick lookup
		$directScoresMap = [];
		foreach ($directScores as $score) {
			$directScoresMap[$score->getId()] = $score;
		}

		foreach ($directScoreInfos as $info) {
			$score = $directScoresMap[$info['id']] ?? null;
			if ($score === null) {
				continue;
			}
			$scoreData = $score->jsonSerialize();
			if ($isIndexed) {
				$scoreData['index'] = $info['index'];
			}
			$scores[] = $scoreData;
		}

		// Get scores from score books - use batch fetch for efficiency
		// The score already has scoreBook field set with {id, index} from ScoreMapper
		$scoreBookInfos = $this->linkMapper->findScoreBooksForVersion($effectiveVersionId);
		foreach ($scoreBookInfos as $bookInfo) {
			$bookScoreInfos = $this->scoreBookScoreLinkMapper->findScoresForScoreBook($bookInfo['id']);
			$bookScoreIds = array_map(fn ($info) => $info['score_id'], $bookScoreInfos);
			$bookScores = $this->scoreMapper->findMultiple($bookScoreIds);

			// Create a map for quick lookup
			$bookScoresMap = [];
			foreach ($bookScores as $score) {
				$bookScoresMap[$score->getId()] = $score;
			}

			foreach ($bookScoreInfos as $scoreInfo) {
				$score = $bookScoresMap[$scoreInfo['score_id']] ?? null;
				if ($score === null) {
					continue;
				}
				$scoreData = $score->jsonSerialize();
				$scoreData['viaScoreBook'] = 'true';
				if ($isIndexed) {
					// For indexed collections, the folder collection index is the book's index
					$scoreData['index'] = $bookInfo['index'];
				}
				$scores[] = $scoreData;
			}
		}

		return $scores;
	}

	/**
	 * Get all folder collections containing a specific score (via any version).
	 * Returns information about the folder collections and versions the score is in.
	 *
	 * @param int $scoreId
	 * @return array Array of information about folder collections containing the score
	 * @throws \Exception
	 */
	public function getFolderCollectionsForScore(int $scoreId): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		// Verify the score exists
		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \Exception($this->l->t('Score not found'));
		}

		$folderCollections = [];

		// Get direct version memberships
		$versionInfos = $this->linkMapper->findVersionsForScore($scoreId);
		foreach ($versionInfos as $info) {
			$version = $this->versionMapper->find($info['versionId']);
			$folderCollection = $this->folderCollectionMapper->find($version->getFolderCollectionId());
			$fcData = $folderCollection->jsonSerialize();
			$scoreFcData = [
				'folderCollection' => $fcData,
				'version' => $version->jsonSerialize(),
				'index' => $info['index'],
				'viaScoreBookId' => null,
			];
			$folderCollections[] = $scoreFcData;
		}

		// Also check if score is in a book that's in a folder collection version
		$scoreBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
		if ($scoreBookInfo !== null) {
			$bookVersionInfos = $this->linkMapper->findVersionsForScoreBook($scoreBookInfo['score_book_id']);
			foreach ($bookVersionInfos as $info) {
				$version = $this->versionMapper->find($info['versionId']);
				$folderCollection = $this->folderCollectionMapper->find($version->getFolderCollectionId());
				$fcData = $folderCollection->jsonSerialize();
				$scoreFcData = [
					'folderCollection' => $fcData,
					'version' => $version->jsonSerialize(),
					'index' => $info['index'],
					'viaScoreBookId' => $scoreBookInfo['score_book_id'],
				];
				$folderCollections[] = $scoreFcData;
			}
		}

		return $folderCollections;
	}

	/**
	 * Add a score to a folder collection's active version.
	 *
	 * @param int $scoreId
	 * @param int $folderCollectionId
	 * @param int|null $index
	 * @throws \Exception
	 */
	public function addScoreToFolderCollection(int $scoreId, int $folderCollectionId, ?int $index = null): void {
		// Check if the active version can be updated
		$this->validateActiveVersionForUpdate($folderCollectionId);

		// Get folder collection with active version
		['folderCollection' => $folderCollection, 'activeVersionId' => $activeVersionId] = $this->getFolderCollectionWithActiveVersion($folderCollectionId);

		// Verify the score exists
		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score not found'));
		}

		// Check if the score is part of a score book that's already in the version
		$scoreBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
		if ($scoreBookInfo !== null) {
			if ($this->linkMapper->isScoreBookInVersion($scoreBookInfo['score_book_id'], $activeVersionId)) {
				throw new \InvalidArgumentException($this->l->t('Cannot add score individually when its score book is already in the collection'));
			}
		}

		$isIndexed = $folderCollection->getCollectionType() === FolderCollectionType::INDEXED->value;

		// Validate index requirement based on collection type
		if ($isIndexed) {
			if ($index === null) {
				throw new \InvalidArgumentException($this->l->t('Index is required for indexed folder collections'));
			}
		} else {
			// For alphabetical collections, index should not be provided
			if ($index !== null) {
				throw new \InvalidArgumentException($this->l->t('Index should not be provided for alphabetical folder collections'));
			}
		}

		try {
			$this->linkMapper->addScoreToVersion($scoreId, $activeVersionId, $index);
		} catch (Exception $e) {
			// Check if this is a unique constraint violation for the index
			$lowerMessage = strtolower($e->getMessage());
			if (str_contains($lowerMessage, 'duplicate entry')) {
				if ($isIndexed) {
					throw new \InvalidArgumentException($this->l->t('Index %d or score %d is already used in this folder collection', [$index, $scoreId]));
				}
				// For alphabetical collections, re-adding the same score doesn't change anything
				return;
			}
			throw new \Exception($this->l->t('Failed to add score to folder collection: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Remove a score from a folder collection.
	 *
	 * @param int $scoreId
	 * @param int $folderCollectionId
	 * @throws \Exception
	 */
	public function removeScoreFromFolderCollection(int $scoreId, int $folderCollectionId): void {
		// Check if the active version can be updated
		$this->validateActiveVersionForUpdate($folderCollectionId);

		// Get folder collection with active version
		['activeVersionId' => $activeVersionId] = $this->getFolderCollectionWithActiveVersion($folderCollectionId);

		// Check if score is directly in the version
		if (!$this->linkMapper->isScoreDirectlyInVersion($scoreId, $activeVersionId)) {
			// Check if it's via a score book
			$scoreBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
			if ($scoreBookInfo !== null && $this->linkMapper->isScoreBookInVersion($scoreBookInfo['score_book_id'], $activeVersionId)) {
				throw new \InvalidArgumentException($this->l->t('Cannot remove score that is part of a score book in this collection. Remove the score book instead.'));
			}
			throw new \InvalidArgumentException($this->l->t('Score is not directly in this folder collection'));
		}

		try {
			$this->linkMapper->removeScoreFromVersion($scoreId, $activeVersionId);
		} catch (Exception $e) {
			throw new \Exception($this->l->t('Failed to remove score from folder collection'));
		}
	}

	/**
	 * Add a score book to a folder collection's active version.
	 *
	 * @param int $scoreBookId
	 * @param int $folderCollectionId
	 * @param int|null $index
	 * @throws \Exception
	 */
	public function addScoreBookToFolderCollection(int $scoreBookId, int $folderCollectionId, ?int $index = null): void {
		// Check if the active version can be updated
		$this->validateActiveVersionForUpdate($folderCollectionId);

		// Get folder collection with active version
		['folderCollection' => $folderCollection, 'activeVersionId' => $activeVersionId] = $this->getFolderCollectionWithActiveVersion($folderCollectionId);

		// Verify the score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		// Check that no score from this book is already in the version (batch check for efficiency)
		$bookScoreInfos = $this->scoreBookScoreLinkMapper->findScoresForScoreBook($scoreBookId);
		$bookScoreIds = array_map(fn ($info) => $info['score_id'], $bookScoreInfos);
		$conflictingScoreIds = $this->linkMapper->findScoresDirectlyInVersion($bookScoreIds, $activeVersionId);
		if (!empty($conflictingScoreIds)) {
			throw new \InvalidArgumentException($this->l->t('Cannot add score book when one of its scores is already in the collection'));
		}

		$isIndexed = $folderCollection->getCollectionType() === FolderCollectionType::INDEXED->value;

		// Validate index requirement based on collection type
		if ($isIndexed) {
			if ($index === null) {
				throw new \InvalidArgumentException($this->l->t('Index is required for indexed folder collections'));
			}
		} else {
			if ($index !== null) {
				throw new \InvalidArgumentException($this->l->t('Index should not be provided for alphabetical folder collections'));
			}
		}

		try {
			$this->linkMapper->addScoreBookToVersion($scoreBookId, $activeVersionId, $index);
		} catch (Exception $e) {
			$lowerMessage = strtolower($e->getMessage());
			if (str_contains($lowerMessage, 'duplicate entry')) {
				if ($isIndexed) {
					throw new \InvalidArgumentException($this->l->t('Index %d or score book %d is already used in this folder collection', [$index, $scoreBookId]));
				}
				return;
			}
			throw new \Exception($this->l->t('Failed to add score book to folder collection: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Get score books in a folder collection version.
	 *
	 * @param int $folderCollectionId
	 * @param int|null $versionId Version ID, if null uses active version
	 * @return array Array of score books with index information
	 * @throws \Exception
	 */
	public function getScoreBooksInFolderCollection(int $folderCollectionId, ?int $versionId = null): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		// Verify the folder collection exists
		$fc = $this->findFolderCollectionEntity($folderCollectionId);

		// Resolve and validate version ID
		$effectiveVersionId = $this->resolveVersionId($fc, $versionId);
		if ($effectiveVersionId === null) {
			return []; // No version exists
		}

		$isIndexed = $fc->getCollectionType() === FolderCollectionType::INDEXED->value;

		// Use batch fetch for efficiency
		$bookInfos = $this->linkMapper->findScoreBooksForVersion($effectiveVersionId);
		$bookIds = array_map(fn ($info) => $info['id'], $bookInfos);
		$books = $this->scoreBookMapper->findMultiple($bookIds);

		// Create a map for quick lookup
		$booksMap = [];
		foreach ($books as $book) {
			$booksMap[$book->getId()] = $book;
		}

		$scoreBooks = [];
		foreach ($bookInfos as $info) {
			$scoreBook = $booksMap[$info['id']] ?? null;
			if ($scoreBook === null) {
				continue;
			}
			$bookData = $scoreBook->jsonSerialize();
			if ($isIndexed) {
				$bookData['index'] = $info['index'];
			}
			$scoreBooks[] = $bookData;
		}

		return $scoreBooks;
	}

	/**
	 * Remove a score book from a folder collection's active version.
	 *
	 * @param int $scoreBookId
	 * @param int $folderCollectionId
	 * @throws \Exception
	 */
	public function removeScoreBookFromFolderCollection(int $scoreBookId, int $folderCollectionId): void {
		// Check if the active version can be updated
		$this->validateActiveVersionForUpdate($folderCollectionId);

		// Get folder collection with active version
		['activeVersionId' => $activeVersionId] = $this->getFolderCollectionWithActiveVersion($folderCollectionId);

		try {
			$this->linkMapper->removeScoreBookFromVersion($scoreBookId, $activeVersionId);
		} catch (Exception $e) {
			throw new \Exception($this->l->t('Failed to remove score book from folder collection: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Get all folder collections containing a specific score book (via any version).
	 *
	 * @param int $scoreBookId
	 * @return array Array of information about folder collections containing the score book
	 * @throws \InvalidArgumentException
	 */
	public function getFolderCollectionsForScoreBook(int $scoreBookId): array {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		// Verify the score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		$folderCollections = [];
		$versionInfos = $this->linkMapper->findVersionsForScoreBook($scoreBookId);
		foreach ($versionInfos as $info) {
			$version = $this->versionMapper->find($info['versionId']);
			$folderCollection = $this->folderCollectionMapper->find($version->getFolderCollectionId());
			// Return structure analogous to FolderCollectionScore
			$folderCollections[] = [
				'folderCollection' => $folderCollection->jsonSerialize(),
				'version' => $version->jsonSerialize(),
				'index' => $info['index'],
			];
		}

		return $folderCollections;
	}

	/**
	 * Get total score count in a folder collection version (including scores from score books).
	 *
	 * @param int $versionId
	 * @return int
	 * @throws Exception
	 */
	public function getTotalScoreCountForVersion(int $versionId): int {
		// Count directly linked scores
		$directCount = $this->linkMapper->countScoresInVersion($versionId);

		// Count scores from score books
		$bookInfos = $this->linkMapper->findScoreBooksForVersion($versionId);
		$bookScoreCount = 0;
		foreach ($bookInfos as $info) {
			$bookScoreCount += $this->scoreBookScoreLinkMapper->countScoresInScoreBook($info['id']);
		}

		return $directCount + $bookScoreCount;
	}

	/**
	 * Validate that the active version exists and can be updated.
	 *
	 * @param int $folderCollectionId
	 * @throws \Exception
	 */
	private function validateActiveVersionForUpdate(int $folderCollectionId): void {
		$this->authorizationService->authorizePolicy($this->folderCollectionPolicy, PolicyInterface::ACTION_UPDATE);

		try {
			$folderCollection = $this->folderCollectionMapper->find($folderCollectionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection not found'));
		}

		$activeVersionId = $folderCollection->getActiveVersionId();
		if ($activeVersionId === null) {
			throw new \InvalidArgumentException($this->l->t('No active version exists for this folder collection'));
		}

		// Check version policy (ensures version is active)
		try {
			$version = $this->versionMapper->find($activeVersionId);
			$this->authorizationService->authorizePolicy($this->versionPolicy, PolicyInterface::ACTION_UPDATE, $version);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Folder collection version not found'));
		}
	}
}
