<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\ScoreBookService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type OrchestraScoresManagerScoreBook from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerScore from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerFolderCollectionScoreBook from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ScoreBookApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ScoreBookService $scoreBookService,
		private readonly FolderCollectionService $folderCollectionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return all available score books
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerScoreBook>, array{}> the list of score books
	 *
	 * 200: Successful response with the list of score books
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scorebooks')]
	public function getScoreBooks(): DataResponse {
		$scoreBooks = $this->callService(fn () => $this->scoreBookService->getAllScoreBooks());
		return new DataResponse($scoreBooks);
	}

	/**
	 * Get a specific score book by ID
	 *
	 * @param int $id The ID of the score book
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerScoreBook, array{}> the score book
	 *
	 * 200: Successful response with the score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scorebooks/{id}')]
	public function getScoreBook(int $id): DataResponse {
		$scoreBook = $this->callService(fn () => $this->scoreBookService->getScoreBookById($id));
		return new DataResponse($scoreBook);
	}

	/**
	 * Create a new score book
	 *
	 * @param string $title The title of the score book
	 * @param string|null $titleShort The short title of the score book
	 * @param string|null $composer The composer of the score book
	 * @param string|null $arranger The arranger of the score book
	 * @param string|null $editor The editor of the score book
	 * @param string|null $publisher The publisher of the score book
	 * @param int|null $year The year of publication
	 * @param float|null $difficulty The difficulty level
	 * @param string|null $defects Any defects
	 * @param string|null $physicalCopiesStatus The status of physical copies
	 * @param list<int>|null $tagIds optional list of tag ids to link
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerScoreBook, array{}> the created score book
	 *
	 * 201: Successful creation of the score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scorebooks')]
	public function postScoreBook(
		string $title,
		?string $titleShort = null,
		?string $composer = null,
		?string $arranger = null,
		?string $editor = null,
		?string $publisher = null,
		?int $year = null,
		?float $difficulty = null,
		?string $defects = null,
		?string $physicalCopiesStatus = null,
		?array $tagIds = null,
	): DataResponse {
		$scoreBook = ScoreBook::fromParams([
			'title' => $title,
			'titleShort' => $titleShort,
			'composer' => $composer,
			'arranger' => $arranger,
			'editor' => $editor,
			'publisher' => $publisher,
			'year' => $year,
			'difficulty' => $difficulty,
			'defects' => $defects,
			'physicalCopiesStatus' => $physicalCopiesStatus,
		]);

		$created = $this->callService(fn () => $this->scoreBookService->createScoreBook($scoreBook, $tagIds));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Update an existing score book
	 *
	 * @param int $id The ID of the score book to update
	 * @param string|null $title The title of the score book
	 * @param string|null $titleShort The short title of the score book
	 * @param string|null $composer The composer of the score book
	 * @param string|null $arranger The arranger of the score book
	 * @param string|null $editor The editor of the score book
	 * @param string|null $publisher The publisher of the score book
	 * @param int|null $year The year of publication
	 * @param float|null $difficulty The difficulty level
	 * @param string|null $defects Any defects
	 * @param string|null $physicalCopiesStatus The status of physical copies
	 * @param list<int>|null $tagIds optional list of tag ids to link
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerScoreBook, array{}> the updated score book
	 *
	 * 200: Successful update of the score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 *
	 * @psalm-suppress PossiblyUnusedParam - extracted from request params
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/scorebooks/{id}')]
	public function patchScoreBook(
		int $id,
		?string $title = null,
		?string $titleShort = null,
		?string $composer = null,
		?string $arranger = null,
		?string $editor = null,
		?string $publisher = null,
		?int $year = null,
		?float $difficulty = null,
		?string $defects = null,
		?string $physicalCopiesStatus = null,
		?array $tagIds = null,
	): DataResponse {
		$scoreBook = $this->callService(fn () => $this->scoreBookService->findScoreBookEntity($id));
		$params = $this->request->getParams();

		// drop NC-added "_route" param if exists
		unset($params['_route']);

		/** @var string $field
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress MixedArgumentTypeCoercion
		 */
		foreach ($params as $field => $value) {
			if ($field === 'tagIds') {
				continue;
			}
			$scoreBook->{'set' . ucfirst($field)}($value);
		}

		$updated = $this->callService(fn () => $this->scoreBookService->updateScoreBook($scoreBook, $tagIds));
		return new DataResponse($updated, Http::STATUS_OK);
	}

	/**
	 * Delete a score book
	 *
	 * @param int $id The ID of the score book to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the score book
	 * @throws OCSBadRequestException Invalid request parameters (e.g., book has linked scores)
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/scorebooks/{id}')]
	public function deleteScoreBook(int $id): DataResponse {
		$this->callService(fn () => $this->scoreBookService->deleteScoreBook($id));
		return new DataResponse([]);
	}

	/**
	 * Get all scores in a score book
	 *
	 * @param int $id The ID of the score book
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerScore>, array{}> the list of scores
	 *
	 * 200: Successful response with the list of scores
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scorebooks/{id}/scores')]
	public function getScoreBookScores(int $id): DataResponse {
		$scores = $this->callService(fn () => $this->scoreBookService->getScoresInScoreBook($id));
		return new DataResponse($scores);
	}

	/**
	 * Add a score to a score book
	 *
	 * Conditions:
	 * - The score book must exist
	 * - The score must exist
	 * - The score must not already be part of any score book (a score can only belong to one book)
	 * - The index position must not already be occupied in the book
	 *
	 * @param int $id The ID of the score book
	 * @param int $scoreId The ID of the score to add
	 * @param int $index The index position for the score in the book
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array{}, array{}> empty response
	 *
	 * 201: Successfully added score to score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scorebooks/{id}/scores')]
	public function postScoreBookScore(int $id, int $scoreId, int $index): DataResponse {
		$this->callService(fn () => $this->scoreBookService->addScoreToScoreBook($id, $scoreId, $index));
		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Add multiple scores to a score book in one request
	 *
	 * Conditions (for each score):
	 * - The score book must exist
	 * - Each score must exist
	 * - Each score must not already be part of any score book
	 * - Each index position must not already be occupied in the book
	 * - Index positions in the request must be unique
	 *
	 * @param int $id The ID of the score book
	 * @param list<array{scoreId: int, index: int}> $scores Array of score data with scoreId and index
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array{}, array{}> empty response
	 *
	 * 201: Successfully added scores to score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scorebooks/{id}/scores/batch')]
	public function postScoreBookScoresBatch(int $id, array $scores): DataResponse {
		$this->callService(fn () => $this->scoreBookService->addScoresToScoreBook($id, $scores));
		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Remove a score from a score book
	 *
	 * Conditions:
	 * - The score book must exist
	 * - The score must be part of this specific score book
	 *
	 * @param int $id The ID of the score book
	 * @param int $scoreId The ID of the score to remove
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successfully removed score from score book
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/scorebooks/{id}/scores/{scoreId}')]
	public function deleteScoreBookScore(int $id, int $scoreId): DataResponse {
		$this->callService(fn () => $this->scoreBookService->removeScoreFromScoreBook($id, $scoreId));
		return new DataResponse([]);
	}

	/**
	 * Get folder collections containing this score book
	 *
	 * @param int $id The ID of the score book
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerFolderCollectionScoreBook>, array{}> the list of folder collections
	 *
	 * 200: Successful response with the list of folder collections
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scorebooks/{id}/foldercollections')]
	public function getScoreBookFolderCollections(int $id): DataResponse {
		$folderCollections = $this->callService(fn () => $this->folderCollectionService->getFolderCollectionsForScoreBook($id));
		return new DataResponse($folderCollections);
	}
}
