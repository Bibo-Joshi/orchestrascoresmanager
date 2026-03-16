<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Utility;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCP\DB\Exception;
use OCP\IL10N;

/**
 * Helper class for setlist validation operations.
 *
 * This helper provides validation methods for setlist operations,
 * particularly for validating score membership in folder collection versions.
 */
class SetlistValidationHelper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ScoreFolderCollectionLinkMapper $scoreFolderCollectionLinkMapper,
		private readonly ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Validate that a score belongs to a specific folder collection version.
	 *
	 * Checks if the score is either:
	 * 1. Directly in the folder collection version, or
	 * 2. In a score book that belongs to the folder collection version
	 *
	 * @param int $scoreId The ID of the score to validate
	 * @param int $versionId The ID of the folder collection version
	 * @throws InvalidArgumentException If the score does not belong to the version
	 * @throws Exception
	 */
	public function validateScoreInFolderCollectionVersion(int $scoreId, int $versionId): void {
		// Check if score is directly in the version
		if ($this->scoreFolderCollectionLinkMapper->isScoreDirectlyInVersion($scoreId, $versionId)) {
			return;
		}

		// Check if score is in a score book that belongs to the version
		$scoreBookInfo = $this->scoreBookScoreLinkMapper->findScoreBookForScore($scoreId);
		if ($scoreBookInfo !== null) {
			$scoreBookId = $scoreBookInfo['score_book_id'];
			if ($this->scoreFolderCollectionLinkMapper->isScoreBookInVersion($scoreBookId, $versionId)) {
				return;
			}
		}

		throw new InvalidArgumentException(
			$this->l->t('Score must belong to the folder collection version specified in the setlist')
		);
	}
}
