<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\Enum\FolderCollectionType;
use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\FolderCollectionPolicy;
use OCA\OrchestraScoresManager\Policy\FolderCollectionVersionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FolderCollectionService.
 */
final class FolderCollectionServiceTest extends TestCase {
	private FolderCollectionMapper $folderCollectionMapper;
	private FolderCollectionVersionMapper $versionMapper;
	private ScoreFolderCollectionLinkMapper $linkMapper;
	private ScoreMapper $scoreMapper;
	private ScoreBookMapper $scoreBookMapper;
	private ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper;
	private AuthorizationService $authorizationService;
	private FolderCollectionPolicy $folderCollectionPolicy;
	private FolderCollectionVersionPolicy $versionPolicy;
	private IL10N $l10n;
	private FolderCollectionService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->folderCollectionMapper = $this->createMock(FolderCollectionMapper::class);
		$this->versionMapper = $this->createMock(FolderCollectionVersionMapper::class);
		$this->linkMapper = $this->createMock(ScoreFolderCollectionLinkMapper::class);
		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->scoreBookMapper = $this->createMock(ScoreBookMapper::class);
		$this->scoreBookScoreLinkMapper = $this->createMock(ScoreBookScoreLinkMapper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->folderCollectionPolicy = $this->createMock(FolderCollectionPolicy::class);
		$this->versionPolicy = $this->createMock(FolderCollectionVersionPolicy::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new FolderCollectionService(
			$this->folderCollectionMapper,
			$this->versionMapper,
			$this->linkMapper,
			$this->scoreMapper,
			$this->scoreBookMapper,
			$this->scoreBookScoreLinkMapper,
			$this->authorizationService,
			$this->folderCollectionPolicy,
			$this->versionPolicy,
			$this->l10n
		);
	}

	public function testGetFolderCollectionByIdRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);

		$version = new FolderCollectionVersion();
		$version->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('findLatestVersion')
			->with(1)
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('countScoresInVersion')
			->with(10)
			->willReturn(5);

		$this->linkMapper->expects($this->once())
			->method('findScoreBooksForVersion')
			->with(10)
			->willReturn([]);

		$result = $this->service->getFolderCollectionById(1);

		$this->assertIsArray($result);
		$this->assertSame(5, $result['scoreCount']);
	}

	public function testGetAllFolderCollectionsRequiresAuthorization(): void {
		$fc1 = new FolderCollection();
		$fc1->setId(1);
		$fc2 = new FolderCollection();
		$fc2->setId(2);

		$version1 = new FolderCollectionVersion();
		$version1->setId(10);
		$version2 = new FolderCollectionVersion();
		$version2->setId(20);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->folderCollectionPolicy, PolicyInterface::ACTION_READ);

		$this->folderCollectionMapper->expects($this->once())
			->method('findAll')
			->willReturn([$fc1, $fc2]);

		$this->versionMapper->expects($this->exactly(2))
			->method('findLatestVersion')
			->willReturnCallback(fn ($id) => $id === 1 ? $version1 : $version2);

		$this->linkMapper->expects($this->exactly(2))
			->method('countScoresInVersion')
			->willReturn(0);

		$this->linkMapper->expects($this->exactly(2))
			->method('findScoreBooksForVersion')
			->willReturn([]);

		$result = $this->service->getAllFolderCollections();

		$this->assertCount(2, $result);
	}

	// Todo: update to enum FolderCollectionType::* when dropping NC32/PHP8.1
	#[TestWith(['alphabetical'])]
	#[TestWith(['indexed'])]
	public function testCreateFolderCollectionRequiresAuthorization(string $type): void {
		$fc = new FolderCollection();
		$fc->setCollectionType($type);
		$created = clone $fc;
		$created->setId(1);

		$version = new FolderCollectionVersion();
		$version->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->folderCollectionPolicy, PolicyInterface::ACTION_CREATE);

		$this->folderCollectionMapper->expects($this->once())
			->method('insert')
			->with($fc)
			->willReturn($created);

		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturn($version);

		$this->folderCollectionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($fc) => $fc->getActiveVersionId() === 10));

		$result = $this->service->createFolderCollection($fc);

		$this->assertIsArray($result);
		$this->assertSame(0, $result['scoreCount']);
	}

	public function testCreateFolderCollectionThrowsOnInvalidType(): void {
		$fc = new FolderCollection();
		$fc->setCollectionType('invalid');

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid collection type');

		$this->service->createFolderCollection($fc);
	}

	public function testUpdateFolderCollectionRequiresAuthorization(): void {
		$existing = new FolderCollection();
		$existing->setId(1);
		$existing->setCollectionType(FolderCollectionType::ALPHABETICAL->value);

		$updated = clone $existing;
		$updated->setTitle('Updated Name');

		$version = new FolderCollectionVersion();
		$version->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->folderCollectionPolicy, PolicyInterface::ACTION_UPDATE);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($existing);

		$this->folderCollectionMapper->expects($this->once())
			->method('update')
			->with($updated)
			->willReturn($updated);

		$this->versionMapper->expects($this->once())
			->method('findLatestVersion')
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('countScoresInVersion')
			->willReturn(0);

		$this->linkMapper->expects($this->once())
			->method('findScoreBooksForVersion')
			->willReturn([]);

		$this->service->updateFolderCollection($updated);
	}

	public function testUpdateFolderCollectionThrowsWhenChangingType(): void {
		$existing = new FolderCollection();
		$existing->setId(1);
		$existing->setCollectionType(FolderCollectionType::ALPHABETICAL->value);

		$updated = clone $existing;
		$updated->setCollectionType(FolderCollectionType::INDEXED->value);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($existing);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Collection type cannot be changed');

		$this->service->updateFolderCollection($updated);
	}

	public function testDeleteFolderCollectionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->folderCollectionPolicy, PolicyInterface::ACTION_DELETE);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($fc);

		$this->folderCollectionMapper->expects($this->once())
			->method('delete')
			->with($fc);

		$this->service->deleteFolderCollection(1);
	}

	public function testGetScoresInFolderCollectionReturnsDirectAndBookScores(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);

		$score1 = new Score();
		$score1->setId(100);
		$score2 = new Score();
		$score2->setId(200);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setFolderCollectionId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('findScoresForVersion')
			->with(10)
			->willReturn([['id' => 100, 'index' => null]]);

		$this->scoreMapper->expects($this->once())
			->method('findMultiple')
			->with([100])
			->willReturn([$score1]);

		$this->linkMapper->expects($this->once())
			->method('findScoreBooksForVersion')
			->willReturn([]);

		$result = $this->service->getScoresInFolderCollection(1);

		$this->assertCount(1, $result);
	}

	public function testAddScoreToFolderCollectionRequiresUpdateAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setFolderCollectionId(1);
		$version->setValidTo(null);

		$score = new Score();
		$score->setId(100);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->with(1)
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($version);

		$this->scoreMapper->expects($this->once())
			->method('find')
			->with(100)
			->willReturn($score);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->with(100)
			->willReturn(null);

		$this->linkMapper->expects($this->once())
			->method('addScoreToVersion')
			->with(100, 10, 5);

		$this->service->addScoreToFolderCollection(100, 1, 5);
	}

	public function testAddScoreToFolderCollectionThrowsWhenNoActiveVersion(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(null);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No active version exists');

		$this->service->addScoreToFolderCollection(100, 1, 5);
	}

	public function testAddScoreToIndexedCollectionRequiresIndex(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setValidTo(null);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->scoreMapper->expects($this->once())
			->method('find')
			->willReturn(new Score());

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->willReturn(null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Index is required for indexed folder collections');

		$this->service->addScoreToFolderCollection(100, 1, null);
	}

	public function testAddScoreToAlphabeticalCollectionRejectsIndex(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);
		$fc->setCollectionType(FolderCollectionType::ALPHABETICAL->value);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setValidTo(null);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->scoreMapper->expects($this->once())
			->method('find')
			->willReturn(new Score());

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->willReturn(null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Index should not be provided for alphabetical');

		$this->service->addScoreToFolderCollection(100, 1, 5);
	}

	public function testRemoveScoreFromFolderCollectionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setValidTo(null);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('isScoreDirectlyInVersion')
			->with(100, 10)
			->willReturn(true);

		$this->linkMapper->expects($this->once())
			->method('removeScoreFromVersion')
			->with(100, 10);

		$this->service->removeScoreFromFolderCollection(100, 1);
	}

	public function testAddScoreBookToFolderCollectionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);
		$fc->setCollectionType(FolderCollectionType::INDEXED->value);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setValidTo(null);

		$scoreBook = new ScoreBook();
		$scoreBook->setId(50);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->with(50)
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoresForScoreBook')
			->with(50)
			->willReturn([]);

		$this->linkMapper->expects($this->once())
			->method('findScoresDirectlyInVersion')
			->willReturn([]);

		$this->linkMapper->expects($this->once())
			->method('addScoreBookToVersion')
			->with(50, 10, 3);

		$this->service->addScoreBookToFolderCollection(50, 1, 3);
	}

	public function testRemoveScoreBookFromFolderCollectionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);
		$fc->setActiveVersionId(10);

		$version = new FolderCollectionVersion();
		$version->setId(10);
		$version->setValidTo(null);

		$this->authorizationService->expects($this->exactly(2))
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->exactly(2))
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('removeScoreBookFromVersion')
			->with(50, 10);

		$this->service->removeScoreBookFromFolderCollection(50, 1);
	}

	public function testGetTotalScoreCountForVersionIncludesBookScores(): void {
		$this->linkMapper->expects($this->once())
			->method('countScoresInVersion')
			->with(10)
			->willReturn(5);

		$this->linkMapper->expects($this->once())
			->method('findScoreBooksForVersion')
			->with(10)
			->willReturn([['id' => 1], ['id' => 2]]);

		$this->scoreBookScoreLinkMapper->expects($this->exactly(2))
			->method('countScoresInScoreBook')
			->willReturnCallback(fn ($id) => $id === 1 ? 3 : 7);

		$result = $this->service->getTotalScoreCountForVersion(10);

		$this->assertSame(15, $result); // 5 + 3 + 7
	}

	public function testFindFolderCollectionEntityThrowsWhenNotFound(): void {
		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Folder collection not found');

		$this->service->findFolderCollectionEntity(999);
	}
}
