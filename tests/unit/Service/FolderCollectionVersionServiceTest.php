<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\Db\FolderCollectionMapper;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersion;
use OCA\OrchestraScoresManager\Db\FolderCollectionVersionMapper;
use OCA\OrchestraScoresManager\Db\ScoreFolderCollectionLinkMapper;
use OCA\OrchestraScoresManager\Policy\FolderCollectionVersionPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\FolderCollectionVersionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FolderCollectionVersionService.
 */
final class FolderCollectionVersionServiceTest extends TestCase {
	private FolderCollectionVersionMapper $versionMapper;
	private FolderCollectionMapper $folderCollectionMapper;
	private ScoreFolderCollectionLinkMapper $linkMapper;
	private AuthorizationService $authorizationService;
	private FolderCollectionVersionPolicy $versionPolicy;
	private IL10N $l10n;
	private FolderCollectionVersionService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->versionMapper = $this->createMock(FolderCollectionVersionMapper::class);
		$this->folderCollectionMapper = $this->createMock(FolderCollectionMapper::class);
		$this->linkMapper = $this->createMock(ScoreFolderCollectionLinkMapper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->versionPolicy = $this->createMock(FolderCollectionVersionPolicy::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new FolderCollectionVersionService(
			$this->versionMapper,
			$this->folderCollectionMapper,
			$this->linkMapper,
			$this->authorizationService,
			$this->versionPolicy,
			$this->l10n
		);
	}

	public function testGetVersionByIdRequiresAuthorization(): void {
		$version = new FolderCollectionVersion();
		$version->setId(1);
		$version->setFolderCollectionId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->versionPolicy, PolicyInterface::ACTION_READ);

		$this->versionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($version);

		$result = $this->service->getVersionById(1);

		$this->assertIsArray($result);
	}

	public function testGetVersionByIdThrowsWhenNotFound(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->versionMapper->expects($this->once())
			->method('find')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Folder collection version not found');

		$this->service->getVersionById(999);
	}

	public function testGetVersionsForFolderCollectionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);

		$version1 = new FolderCollectionVersion();
		$version1->setId(10);
		$version2 = new FolderCollectionVersion();
		$version2->setId(20);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->versionPolicy, PolicyInterface::ACTION_READ);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('findAllForFolderCollection')
			->with(1)
			->willReturn([$version1, $version2]);

		$result = $this->service->getVersionsForFolderCollection(1);

		$this->assertCount(2, $result);
	}

	public function testCreateVersionRequiresAuthorization(): void {
		$fc = new FolderCollection();
		$fc->setId(1);

		$version = new FolderCollectionVersion();
		$version->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->versionPolicy, PolicyInterface::ACTION_CREATE);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(false);

		$this->versionMapper->expects($this->once())
			->method('findActiveVersion')
			->with(1)
			->willReturn(null);

		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturn($version);

		$this->folderCollectionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($fc) => $fc->getActiveVersionId() === 10));

		$result = $this->service->createVersion(1, '2024-01-01');

		$this->assertIsArray($result);
	}

	public function testCreateVersionThrowsWhenOverlapping(): void {
		$fc = new FolderCollection();

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(true);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('overlaps with an existing version');

		$this->service->createVersion(1, '2024-01-01');
	}

	public function testCreateVersionThrowsWhenActiveAlreadyExists(): void {
		$fc = new FolderCollection();
		$existingActive = new FolderCollectionVersion();

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(false);

		$this->versionMapper->expects($this->once())
			->method('findActiveVersion')
			->willReturn($existingActive);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('active version already exists');

		$this->service->createVersion(1, '2024-01-01', null);
	}

	public function testCreateVersionCanCopyFromExisting(): void {
		$fc = new FolderCollection();
		$version = new FolderCollectionVersion();
		$version->setId(10);
		$sourceVersion = new FolderCollectionVersion();
		$sourceVersion->setId(5);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(false);

		// Only called once to verify source version exists before copying
		$this->versionMapper->expects($this->once())
			->method('find')
			->with(5)
			->willReturn($sourceVersion);

		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturn($version);

		$this->linkMapper->expects($this->once())
			->method('copyLinksToVersion')
			->with(5, 10);

		$this->service->createVersion(1, '2024-01-01', '2024-12-31', 5);
	}

	public function testStartNewVersionDeactivatesCurrentActive(): void {
		$fc = new FolderCollection();
		$fc->setId(1);

		$activeVersion = new FolderCollectionVersion();
		$activeVersion->setId(5);
		$activeVersion->setFolderCollectionId(1);
		$activeVersion->setValidFrom(new \DateTimeImmutable('2024-01-01'));
		$activeVersion->setValidTo(null);

		$newVersion = new FolderCollectionVersion();
		$newVersion->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->versionPolicy, PolicyInterface::ACTION_CREATE);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('findActiveVersion')
			->willReturn($activeVersion);

		$this->versionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($v) => $v->getValidTo() !== null));

		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturn($newVersion);

		$this->linkMapper->expects($this->once())
			->method('copyLinksToVersion')
			->with(5, 10);

		$this->folderCollectionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($fc) => $fc->getActiveVersionId() === 10));

		$this->service->startNewVersion(1, '2024-06-01');
	}

	public function testStartNewVersionThrowsIfNewVersionBeforeActive(): void {
		$fc = new FolderCollection();
		$activeVersion = new FolderCollectionVersion();
		$activeVersion->setValidFrom(new \DateTimeImmutable('2024-06-01'));

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('findActiveVersion')
			->willReturn($activeVersion);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must start at least one day after');

		$this->service->startNewVersion(1, '2024-01-01');
	}

	public function testUpdateVersionRequiresAuthorization(): void {
		$version = new FolderCollectionVersion();
		$version->setId(1);
		$version->setFolderCollectionId(10);
		$version->setValidFrom(new \DateTimeImmutable('2024-01-01'));
		$version->setValidTo(null);

		$fc = new FolderCollection();
		$fc->setId(10);
		$fc->setActiveVersionId(1); // Set to match the version being updated

		$this->versionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($version);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->versionPolicy, PolicyInterface::ACTION_UPDATE, $version);

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(false);

		$this->versionMapper->expects($this->once())
			->method('update')
			->willReturn($version);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($fc);

		$this->folderCollectionMapper->expects($this->once())
			->method('update');

		$this->service->updateVersion(1, '2024-12-31');
	}

	public function testUpdateVersionClearsActiveVersionOnDeactivation(): void {
		$version = new FolderCollectionVersion();
		$version->setId(1);
		$version->setFolderCollectionId(10);
		$version->setValidFrom(new \DateTimeImmutable('2024-01-01'));

		$fc = new FolderCollection();
		$fc->setId(10);
		$fc->setActiveVersionId(1);

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->versionMapper->expects($this->once())
			->method('hasOverlappingVersion')
			->willReturn(false);

		$this->versionMapper->expects($this->once())
			->method('update')
			->willReturn($version);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->folderCollectionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($fc) => $fc->getActiveVersionId() === null));

		$this->service->updateVersion(1, '2024-12-31');
	}

	public function testGetActiveVersionIdReturnsActiveVersion(): void {
		$fc = new FolderCollection();
		$fc->setActiveVersionId(10);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($fc);

		$result = $this->service->getActiveVersionId(1);

		$this->assertSame(10, $result);
	}

	public function testGetActiveVersionIdReturnsNullWhenNoActive(): void {
		$fc = new FolderCollection();
		$fc->setActiveVersionId(null);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$result = $this->service->getActiveVersionId(1);

		$this->assertNull($result);
	}

	public function testGetLatestVersionIdReturnsLatest(): void {
		$fc = new FolderCollection();
		$version = new FolderCollectionVersion();
		$version->setId(10);

		$this->folderCollectionMapper->expects($this->once())
			->method('find')
			->willReturn($fc);

		$this->versionMapper->expects($this->once())
			->method('findLatestVersion')
			->with(1)
			->willReturn($version);

		$result = $this->service->getLatestVersionId(1);

		$this->assertSame(10, $result);
	}

	public function testIsVersionActiveReturnsTrueForActiveVersion(): void {
		$version = new FolderCollectionVersion();
		$version->setValidTo(null);

		$this->versionMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($version);

		$result = $this->service->isVersionActive(1);

		$this->assertTrue($result);
	}

	public function testIsVersionActiveReturnsFalseForInactiveVersion(): void {
		$version = new FolderCollectionVersion();
		$version->setValidTo(new \DateTimeImmutable('2024-12-31'));

		$this->versionMapper->expects($this->once())
			->method('find')
			->willReturn($version);

		$result = $this->service->isVersionActive(1);

		$this->assertFalse($result);
	}

	public function testFindVersionEntityThrowsWhenNotFound(): void {
		$this->versionMapper->expects($this->once())
			->method('find')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Folder collection version not found');

		$this->service->findVersionEntity(999);
	}
}
