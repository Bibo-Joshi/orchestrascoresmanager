<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\AppInfo\Application;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCA\OrchestraScoresManager\Service\ScoreBookService;
use OCA\OrchestraScoresManager\Service\SetlistService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly PermissionService $permissionService,
		private readonly IInitialState $initialStateService,
		private readonly IURLGenerator $urlGenerator,
		private readonly ScoreMapper $scoreMapper,
		private readonly TagMapper $tagMapper,
		private readonly ScoreBookService $scoreBookService,
		private readonly FolderCollectionService $folderCollectionService,
		private readonly SetlistService $setlistService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): RedirectResponse {
		// page.pages: page refers to *Page*Controller, pages refers to pages() method
		$url = $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.pages');
		return new RedirectResponse($url . 'scores');
	}

	/**
	 * Main page handler for SPA routes.
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/scores', postfix: 'scores')]
	#[FrontpageRoute(verb: 'GET', url: '/scorebooks', postfix: 'scorebooks')]
	#[FrontpageRoute(verb: 'GET', url: '/scorebooks/{id}', postfix: 'scorebook')]
	#[FrontpageRoute(verb: 'GET', url: '/foldercollections', postfix: 'foldercollections')]
	#[FrontpageRoute(verb: 'GET', url: '/foldercollections/{id}', postfix: 'foldercollection')]
	#[FrontpageRoute(verb: 'GET', url: '/setlists', postfix: 'setlists')]
	#[FrontpageRoute(verb: 'GET', url: '/setlists/{id}', postfix: 'setlist')]
	public function pages(): TemplateResponse {

		// Set initial state for the frontend
		$this->initialStateService->provideInitialState(
			'editable',
			$this->permissionService->canCurrentUserEdit()
		);

		// Provide the list of scores as initial state for the scores page
		$scores = $this->scoreMapper->findAll();
		$plainScores = [];
		foreach ($scores as $score) {
			// make sure we serialize each Score entity to a plain array
			if (method_exists($score, 'jsonSerialize')) {
				$plainScores[] = $score->jsonSerialize();
			} else {
				$plainScores[] = (array)$score;
			}
		}
		$this->initialStateService->provideInitialState(
			'scores',
			$plainScores
		);

		// Provide tags
		$tags = $this->tagMapper->findAll();
		$plainTags = [];
		foreach ($tags as $tag) {
			if (method_exists($tag, 'jsonSerialize')) {
				$plainTags[] = $tag->jsonSerialize();
			} else {
				$plainTags[] = (array)$tag;
			}
		}
		$this->initialStateService->provideInitialState('tags', $plainTags);

		$this->initialStateService->provideInitialState(
			'scoreBooks',
			$this->scoreBookService->getAllScoreBooks()
		);

		$this->initialStateService->provideInitialState(
			'folderCollections',
			$this->folderCollectionService->getAllFolderCollections()
		);

		$this->initialStateService->provideInitialState(
			'setlists',
			$this->setlistService->getSetlists('all', null, null)
		);

		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}
}
