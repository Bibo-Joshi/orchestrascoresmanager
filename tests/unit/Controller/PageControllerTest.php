<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\AppInfo\Application;
use OCA\OrchestraScoresManager\Controller\PageController;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCA\OrchestraScoresManager\Service\ScoreBookService;
use OCA\OrchestraScoresManager\Service\SetlistService;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PageController.
 */
final class PageControllerTest extends TestCase {
	private PermissionService $permissionService;
	private IInitialState $initialStateService;
	private IURLGenerator $urlGenerator;
	private ScoreMapper $scoreMapper;
	private TagMapper $tagMapper;
	private ScoreBookService $scoreBookService;
	private FolderCollectionService $folderCollectionService;
	private SetlistService $setlistService;
	private IRequest $request;
	private PageController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionService = $this->createMock(PermissionService::class);
		$this->initialStateService = $this->createMock(IInitialState::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->scoreBookService = $this->createMock(ScoreBookService::class);
		$this->folderCollectionService = $this->createMock(FolderCollectionService::class);
		$this->setlistService = $this->createMock(SetlistService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new PageController(
			Application::APP_ID,
			$this->request,
			$this->permissionService,
			$this->initialStateService,
			$this->urlGenerator,
			$this->scoreMapper,
			$this->tagMapper,
			$this->scoreBookService,
			$this->folderCollectionService,
			$this->setlistService
		);
	}

	public function testIndexRedirectsToScoresPage(): void {
		$expectedUrl = '/apps/orchestrascoresmanager/scores';

		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(Application::APP_ID . '.page.pages')
			->willReturn('/apps/orchestrascoresmanager/');

		$response = $this->controller->index();

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertEquals('/apps/orchestrascoresmanager/scores', $response->getRedirectURL());
	}

	public function testPagesReturnsTemplateResponse(): void {
		$this->permissionService->expects($this->once())
			->method('canCurrentUserEdit')
			->willReturn(true);

		$this->scoreMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$this->tagMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$this->scoreBookService->expects($this->once())
			->method('getAllScoreBooks')
			->willReturn([]);

		$this->folderCollectionService->expects($this->once())
			->method('getAllFolderCollections')
			->willReturn([]);

		$this->setlistService->expects($this->once())
			->method('getSetlists')
			->with('all', null, null)
			->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState');

		$response = $this->controller->pages();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('index', $response->getTemplateName());
		$this->assertEquals('user', $response->getRenderAs());
	}

	public function testPagesProvidesEditableState(): void {
		$this->permissionService->expects($this->once())
			->method('canCurrentUserEdit')
			->willReturn(false);

		$this->scoreMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$this->tagMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$this->scoreBookService->expects($this->once())
			->method('getAllScoreBooks')
			->willReturn([]);

		$this->folderCollectionService->expects($this->once())
			->method('getAllFolderCollections')
			->willReturn([]);

		$this->setlistService->expects($this->once())
			->method('getSetlists')
			->with('all', null, null)
			->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState')
			->willReturnCallback(function ($key, $value) {
				if ($key === 'editable') {
					$this->assertFalse($value);
				}
			});

		$this->controller->pages();
	}

	public function testPagesProvidesScoresState(): void {
		$mockScore = $this->createMock(\OCA\OrchestraScoresManager\Db\Score::class);
		$mockScore->expects($this->once())
			->method('jsonSerialize')
			->willReturn(['id' => 1, 'title' => 'Test Score']);

		$this->permissionService->method('canCurrentUserEdit')->willReturn(true);
		$this->scoreMapper->expects($this->once())
			->method('findAll')
			->willReturn([$mockScore]);

		$this->tagMapper->method('findAll')->willReturn([]);
		$this->scoreBookService->method('getAllScoreBooks')->willReturn([]);
		$this->folderCollectionService->method('getAllFolderCollections')->willReturn([]);
		$this->setlistService->method('getSetlists')->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState')
			->willReturnCallback(function ($key, $value) {
				if ($key === 'scores') {
					$this->assertEquals([['id' => 1, 'title' => 'Test Score']], $value);
				}
			});

		$this->controller->pages();
	}

	public function testPagesProvidesTagsState(): void {
		$mockTag = $this->createMock(\OCA\OrchestraScoresManager\Db\Tag::class);
		$mockTag->expects($this->once())
			->method('jsonSerialize')
			->willReturn(['id' => 1, 'name' => 'Classical']);

		$this->permissionService->method('canCurrentUserEdit')->willReturn(true);
		$this->scoreMapper->method('findAll')->willReturn([]);
		$this->tagMapper->expects($this->once())
			->method('findAll')
			->willReturn([$mockTag]);

		$this->scoreBookService->method('getAllScoreBooks')->willReturn([]);
		$this->folderCollectionService->method('getAllFolderCollections')->willReturn([]);
		$this->setlistService->method('getSetlists')->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState')
			->willReturnCallback(function ($key, $value) {
				if ($key === 'tags') {
					$this->assertEquals([['id' => 1, 'name' => 'Classical']], $value);
				}
			});

		$this->controller->pages();
	}

	public function testPagesProvidesScoreBooksState(): void {
		$scoreBooks = [['id' => 1, 'title' => 'Book 1']];

		$this->permissionService->method('canCurrentUserEdit')->willReturn(true);
		$this->scoreMapper->method('findAll')->willReturn([]);
		$this->tagMapper->method('findAll')->willReturn([]);
		$this->scoreBookService->expects($this->once())
			->method('getAllScoreBooks')
			->willReturn($scoreBooks);

		$this->folderCollectionService->method('getAllFolderCollections')->willReturn([]);
		$this->setlistService->method('getSetlists')->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState')
			->willReturnCallback(function ($key, $value) use ($scoreBooks) {
				if ($key === 'scoreBooks') {
					$this->assertEquals($scoreBooks, $value);
				}
			});

		$this->controller->pages();
	}

	public function testPagesProvidesFolderCollectionsState(): void {
		$folderCollections = [['id' => 1, 'title' => 'Collection 1']];

		$this->permissionService->method('canCurrentUserEdit')->willReturn(true);
		$this->scoreMapper->method('findAll')->willReturn([]);
		$this->tagMapper->method('findAll')->willReturn([]);
		$this->scoreBookService->method('getAllScoreBooks')->willReturn([]);
		$this->folderCollectionService->expects($this->once())
			->method('getAllFolderCollections')
			->willReturn($folderCollections);
		$this->setlistService->method('getSetlists')->willReturn([]);

		$this->initialStateService->expects($this->exactly(6))
			->method('provideInitialState')
			->willReturnCallback(function ($key, $value) use ($folderCollections) {
				if ($key === 'folderCollections') {
					$this->assertEquals($folderCollections, $value);
				}
			});

		$this->controller->pages();
	}
}
