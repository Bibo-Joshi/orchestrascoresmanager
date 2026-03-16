<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Utility;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Utility\SetlistValidationHelper;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SetlistValidationHelper.
 */
final class SetlistValidationHelperTest extends TestCase {
	private ScoreFolderCollectionLinkMapper $scoreFolderCollectionLinkMapper;
	private ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper;
	private IL10N $l10n;
	private SetlistValidationHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreFolderCollectionLinkMapper = $this->createMock(ScoreFolderCollectionLinkMapper::class);
		$this->scoreBookScoreLinkMapper = $this->createMock(ScoreBookScoreLinkMapper::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text) => $text);

		$this->helper = new SetlistValidationHelper(
			$this->scoreFolderCollectionLinkMapper,
			$this->scoreBookScoreLinkMapper,
			$this->l10n
		);
	}

	public function testValidateScoreInFolderCollectionVersion_ScoreDirectlyInVersion(): void {
		$scoreId = 1;
		$versionId = 100;

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreDirectlyInVersion')
			->with($scoreId, $versionId)
			->willReturn(true);

		$this->scoreBookScoreLinkMapper
			->expects($this->never())
			->method('findScoreBookForScore');

		// Should not throw exception
		$this->helper->validateScoreInFolderCollectionVersion($scoreId, $versionId);
		$this->assertTrue(true); // Assert test completes without exception
	}

	public function testValidateScoreInFolderCollectionVersion_ScoreInBookInVersion(): void {
		$scoreId = 2;
		$versionId = 101;
		$scoreBookId = 50;

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreDirectlyInVersion')
			->with($scoreId, $versionId)
			->willReturn(false);

		$this->scoreBookScoreLinkMapper
			->expects($this->once())
			->method('findScoreBookForScore')
			->with($scoreId)
			->willReturn(['score_book_id' => $scoreBookId]);

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreBookInVersion')
			->with($scoreBookId, $versionId)
			->willReturn(true);

		// Should not throw exception
		$this->helper->validateScoreInFolderCollectionVersion($scoreId, $versionId);
		$this->assertTrue(true); // Assert test completes without exception
	}

	public function testValidateScoreInFolderCollectionVersion_ScoreNotInVersionThrowsException(): void {
		$scoreId = 3;
		$versionId = 102;

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreDirectlyInVersion')
			->with($scoreId, $versionId)
			->willReturn(false);

		$this->scoreBookScoreLinkMapper
			->expects($this->once())
			->method('findScoreBookForScore')
			->with($scoreId)
			->willReturn(null);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Score must belong to the folder collection version specified in the setlist');

		$this->helper->validateScoreInFolderCollectionVersion($scoreId, $versionId);
	}

	public function testValidateScoreInFolderCollectionVersion_ScoreInBookNotInVersionThrowsException(): void {
		$scoreId = 4;
		$versionId = 103;
		$scoreBookId = 51;

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreDirectlyInVersion')
			->with($scoreId, $versionId)
			->willReturn(false);

		$this->scoreBookScoreLinkMapper
			->expects($this->once())
			->method('findScoreBookForScore')
			->with($scoreId)
			->willReturn(['score_book_id' => $scoreBookId]);

		$this->scoreFolderCollectionLinkMapper
			->expects($this->once())
			->method('isScoreBookInVersion')
			->with($scoreBookId, $versionId)
			->willReturn(false);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Score must belong to the folder collection version specified in the setlist');

		$this->helper->validateScoreInFolderCollectionVersion($scoreId, $versionId);
	}
}
