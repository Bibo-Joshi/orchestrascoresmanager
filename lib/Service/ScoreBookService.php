<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\ScoreBookPolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;
use Throwable;

/**
 * Service for managing Score Books.
 */
class ScoreBookService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ScoreBookMapper $scoreBookMapper,
		private readonly ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper,
		private readonly ScoreBookTagLinkMapper $scoreBookTagLinkMapper,
		private readonly ScoreMapper $scoreMapper,
		private readonly TagMapper $tagMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly ScoreBookPolicy $scoreBookPolicy,
		private IL10N $l,
	) {
	}

	/**
	 * Get a score book by its ID with its score count.
	 *
	 * @param int $id
	 * @return array Score book data with scoreCount
	 * @throws \Exception
	 */
	public function getScoreBookById(int $id): array {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_READ);

		$scoreBook = $this->findScoreBookEntity($id);
		$sbData = method_exists($scoreBook, 'jsonSerialize') ? $scoreBook->jsonSerialize() : (array)$scoreBook;
		$sbData['scoreCount'] = $this->scoreBookScoreLinkMapper->countScoresInScoreBook($id);
		return $sbData;
	}

	/**
	 * Get the raw ScoreBook entity by ID.
	 *
	 * @param int $id
	 * @return ScoreBook
	 * @throws \InvalidArgumentException
	 */
	public function findScoreBookEntity(int $id): ScoreBook {
		try {
			return $this->scoreBookMapper->find($id);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}
	}

	/**
	 * Get all score books with their score counts.
	 *
	 * @return array[] Array of score book data with scoreCount
	 * @throws \Exception
	 */
	public function getAllScoreBooks(): array {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_READ);
		$scoreBooks = $this->scoreBookMapper->findAll();
		return $this->enrichWithScoreCounts($scoreBooks);
	}

	/**
	 * Enrich score books with their score counts.
	 *
	 * @param ScoreBook[] $scoreBooks Array of score books
	 * @return array[] Array of score book data with scoreCount
	 */
	private function enrichWithScoreCounts(array $scoreBooks): array {
		$result = [];
		foreach ($scoreBooks as $scoreBook) {
			$sbData = method_exists($scoreBook, 'jsonSerialize') ? $scoreBook->jsonSerialize() : (array)$scoreBook;
			$sbData['scoreCount'] = $this->scoreBookScoreLinkMapper->countScoresInScoreBook($scoreBook->getId());
			$result[] = $sbData;
		}
		return $result;
	}

	/**
	 * Create a new score book.
	 *
	 * @param ScoreBook $scoreBook
	 * @param array|null $tagIds
	 * @return array Score book data with scoreCount
	 * @throws Exception
	 * @throws Throwable
	 */
	public function createScoreBook(ScoreBook $scoreBook, ?array $tagIds = null): array {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_CREATE);

		$created = $this->scoreBookMapper->insert($scoreBook);

		if ($tagIds !== null) {
			$this->validateAndSetTags($created->getId(), $tagIds);
		}

		$result = $this->scoreBookMapper->find($created->getId());
		$sbData = method_exists($result, 'jsonSerialize') ? $result->jsonSerialize() : (array)$result;
		$sbData['scoreCount'] = 0; // New score book has no scores
		return $sbData;
	}

	/**
	 * Update a score book.
	 *
	 * @param ScoreBook $scoreBook
	 * @param array|null $tagIds
	 * @return array Score book data with scoreCount
	 * @throws Exception
	 * @throws Throwable
	 */
	public function updateScoreBook(ScoreBook $scoreBook, ?array $tagIds = null): array {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE, $scoreBook);

		if ($tagIds !== null) {
			$this->validateAndSetTags($scoreBook->getId(), $tagIds);
		}

		// This check here is needed in case only transient properties (like tags) were updated
		// Unfortunately, NC's QBMapper has a bug in handling for this
		// See also https://github.com/nextcloud/server/pull/56337
		$updatedProperties = $scoreBook->getUpdatedFields();
		unset($updatedProperties['id']);
		if (\count($updatedProperties) > 0) {
			$this->scoreBookMapper->update($scoreBook);
		}

		$result = $this->scoreBookMapper->find($scoreBook->getId());
		$sbData = method_exists($result, 'jsonSerialize') ? $result->jsonSerialize() : (array)$result;
		$sbData['scoreCount'] = $this->scoreBookScoreLinkMapper->countScoresInScoreBook($scoreBook->getId());
		return $sbData;
	}

	/**
	 * Delete a score book.
	 *
	 * @param int $id
	 * @throws Exception
	 * @throws \Exception
	 */
	public function deleteScoreBook(int $id): void {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_DELETE);

		try {
			$scoreBook = $this->scoreBookMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		// Check if there are linked scores
		$scoreCount = $this->scoreBookScoreLinkMapper->countScoresInScoreBook($id);
		if ($scoreCount > 0) {
			throw new \InvalidArgumentException($this->l->t('Cannot delete score book with linked scores. Remove all scores first.'));
		}

		// Delete tag links first (cascade should handle this, but be explicit)
		$this->scoreBookTagLinkMapper->deleteAllTagsForScoreBook($id);

		$this->scoreBookMapper->delete($scoreBook);
	}

	/**
	 * Get scores in a score book.
	 *
	 * @param int $scoreBookId
	 * @return Score[]
	 * @throws \Exception
	 */
	public function getScoresInScoreBook(int $scoreBookId): array {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_READ);

		// Verify score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		$scoreInfos = $this->scoreBookScoreLinkMapper->findScoresForScoreBook($scoreBookId);
		$scoreIds = array_map(fn ($info) => $info['score_id'], $scoreInfos);

		if (empty($scoreIds)) {
			return [];
		}

		// Create index map for sorting
		$indexMap = [];
		foreach ($scoreInfos as $info) {
			$indexMap[$info['score_id']] = $info['index'];
		}

		$scores = $this->scoreMapper->findMultiple($scoreIds);

		// Sort by index
		usort($scores, fn (Score $a, Score $b) => ($indexMap[$a->getId()] ?? 0) <=> ($indexMap[$b->getId()] ?? 0));

		return $scores;
	}

	/**
	 * Add a score to a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $scoreId
	 * @param int $index
	 * @throws \Exception
	 */
	public function addScoreToScoreBook(int $scoreBookId, int $scoreId, int $index): void {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		// Verify score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		// Verify score exists
		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score not found'));
		}

		// Check if score is already in a book
		$existingBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
		if ($existingBookInfo !== null) {
			throw new \InvalidArgumentException($this->l->t('Score is already part of a score book'));
		}

		// Check if index is occupied
		if ($this->scoreBookScoreLinkMapper->isIndexOccupied($scoreBookId, $index)) {
			throw new \InvalidArgumentException($this->l->t('Index %d is already occupied in this score book', [$index]));
		}

		try {
			$this->scoreBookScoreLinkMapper->addScoreToScoreBook($scoreBookId, $scoreId, $index);
		} catch (Exception $e) {
			throw new \Exception($this->l->t('Failed to add score to score book: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Add multiple scores to a score book.
	 *
	 * @param int $scoreBookId
	 * @param array<array{scoreId: int, index: int}> $scores Array of score data
	 * @throws \Exception
	 */
	public function addScoresToScoreBook(int $scoreBookId, array $scores): void {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		// Verify score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		// Get existing indices in the score book
		$existingScores = $this->scoreBookScoreLinkMapper->findScoresForScoreBook($scoreBookId);
		$occupiedIndices = array_map(fn ($s) => $s['index'], $existingScores);

		// Validate all scores and collect data for batch insert
		$scoresToAdd = [];
		$newIndices = [];

		foreach ($scores as $scoreData) {
			$scoreId = $scoreData['scoreId'];
			$index = $scoreData['index'];

			// Verify score exists
			try {
				$this->scoreMapper->find($scoreId);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				throw new \InvalidArgumentException($this->l->t('Score %d not found', [$scoreId]));
			}

			// Check if score is already in a book
			$existingBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
			if ($existingBookInfo !== null) {
				throw new \InvalidArgumentException($this->l->t('Score %d is already part of a score book', [$scoreId]));
			}

			// Check for duplicate indices in the request
			if (in_array($index, $newIndices, true)) {
				throw new \InvalidArgumentException($this->l->t('Duplicate index %d in request', [$index]));
			}

			// Check if index is already occupied
			if (in_array($index, $occupiedIndices, true)) {
				throw new \InvalidArgumentException($this->l->t('Index %d is already occupied in this score book', [$index]));
			}

			$newIndices[] = $index;
			$scoresToAdd[] = ['score_id' => $scoreId, 'index' => $index];
		}

		try {
			$this->scoreBookScoreLinkMapper->addScoresToScoreBook($scoreBookId, $scoresToAdd);
		} catch (Exception $e) {
			throw new \Exception($this->l->t('Failed to add scores to score book: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Remove a score from a score book.
	 *
	 * @param int $scoreBookId
	 * @param int $scoreId
	 * @throws \Exception
	 */
	public function removeScoreFromScoreBook(int $scoreBookId, int $scoreId): void {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		try {
			$this->scoreBookScoreLinkMapper->removeScoreFromScoreBook($scoreBookId, $scoreId);
		} catch (Exception $e) {
			throw new \Exception($this->l->t('Failed to remove score from score book: %1$s', [$e->getMessage()]));
		}
	}

	/**
	 * Update score book info for a score (move to different book or change index).
	 *
	 * @param int $scoreId
	 * @param int|null $scoreBookId New score book ID (null to remove from book)
	 * @param int|null $index New index (required if scoreBookId is set)
	 * @throws \Exception
	 * @psalm-suppress PossiblyUnusedMethod - Public API for future use
	 */
	public function updateScoreBookInfo(int $scoreId, ?int $scoreBookId, ?int $index): void {
		$this->authorizationService->authorizePolicy($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		// Verify score exists
		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score not found'));
		}

		// Get current book info
		$currentBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);

		// If removing from book
		if ($scoreBookId === null) {
			if ($currentBookInfo !== null) {
				$this->scoreBookScoreLinkMapper->removeScoreFromAllBooks($scoreId);
			}
			return;
		}

		// Verify new score book exists
		try {
			$this->scoreBookMapper->find($scoreBookId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new \InvalidArgumentException($this->l->t('Score book not found'));
		}

		// If adding to a book, index is required
		if ($index === null) {
			// If we're staying in the same book and only updating something else, keep existing index
			if ($currentBookInfo !== null && $currentBookInfo['score_book_id'] === $scoreBookId) {
				$index = $currentBookInfo['index'];
			} else {
				throw new \InvalidArgumentException($this->l->t('Index is required when adding score to a score book'));
			}
		}

		// Check if index is occupied (by another score)
		$existingAtIndex = $this->scoreBookScoreLinkMapper->findScoresForScoreBook($scoreBookId);
		foreach ($existingAtIndex as $existing) {
			if ($existing['index'] === $index && $existing['score_id'] !== $scoreId) {
				throw new \InvalidArgumentException($this->l->t('Index %d is already occupied in this score book', [$index]));
			}
		}

		// Remove from current book if any
		if ($currentBookInfo !== null) {
			$this->scoreBookScoreLinkMapper->removeScoreFromScoreBook($currentBookInfo['score_book_id'], $scoreId);
		}

		// Add to new book
		$this->scoreBookScoreLinkMapper->addScoreToScoreBook($scoreBookId, $scoreId, $index);
	}

	/**
	 * Validate tag IDs and set them for a score book.
	 *
	 * @param int $scoreBookId
	 * @param array $tagIds
	 * @throws \Exception
	 * @throws Throwable
	 */
	private function validateAndSetTags(int $scoreBookId, array $tagIds): void {
		$validIds = array_map('intval', $tagIds);
		foreach ($validIds as $tid) {
			try {
				$this->tagMapper->find($tid);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|\RuntimeException $e) {
				throw new \Exception($this->l->t('Tag not found: %s', [$tid]) . ' - ' . $e->getMessage());
			}
		}
		$this->scoreBookTagLinkMapper->setTagsForScoreBook($scoreBookId, $validIds);
	}
}
