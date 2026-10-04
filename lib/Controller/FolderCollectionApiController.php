<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\Db\FolderCollection;
use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\FolderCollectionService;
use OCA\OrchestraScoresManager\Service\FolderCollectionVersionService;
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
 * @psalm-import-type OrchestraScoresManagerFolderCollection from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerFolderCollectionVersion from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerScore from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerScoreIndexed from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerScoreBook from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerScoreBookIndexed from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class FolderCollectionApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly FolderCollectionService $folderCollectionService,
		private readonly FolderCollectionVersionService $versionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return all available folder collections
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerFolderCollection>, array{}> the list of folder collections
	 *
	 * 200: Successful response with the list of folder collections
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollections')]
	public function getFolderCollections(): DataResponse {
		$folderCollections = $this->callService(fn () => $this->folderCollectionService->getAllFolderCollections());
		return new DataResponse($folderCollections);
	}

	/**
	 * Get a specific folder collection by ID
	 *
	 * @param int $id The ID of the folder collection
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerFolderCollection, array{}> the folder collection
	 *
	 * 200: Successful response with the folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollections/{id}')]
	public function getFolderCollection(int $id): DataResponse {
		$folderCollection = $this->callService(fn () => $this->folderCollectionService->getFolderCollectionById($id));
		return new DataResponse($folderCollection);
	}

	/**
	 * Create a new folder collection
	 *
	 * @param string $title The title of the folder collection
	 * @param 'alphabetical'|'indexed' $collectionType The type of the folder collection (alphabetical or indexed)
	 * @param string|null $description The description of the folder collection
	 * @param string|null $validFrom The start date for the initial version (Y-m-d format), defaults to today
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerFolderCollection, array{}> the created folder collection
	 *
	 * 201: Successful creation of the folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/foldercollections')]
	public function postFolderCollection(
		string $title,
		string $collectionType,
		?string $description = null,
		?string $validFrom = null,
	): DataResponse {
		$folderCollection = FolderCollection::fromParams([
			'title' => $title,
			'collectionType' => $collectionType,
			'description' => $description,
		]);

		$created = $this->callService(fn () => $this->folderCollectionService->createFolderCollection($folderCollection, $validFrom));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Update an existing folder collection
	 *
	 * @param int $id The ID of the folder collection to update
	 * @param string|null $title The title of the folder collection
	 * @param string|null $description The description of the folder collection
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerFolderCollection, array{}> the updated folder collection
	 *
	 * 200: Successful update of the folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 *
	 * @psalm-suppress PossiblyUnusedParam - extracted from request params
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/foldercollections/{id}')]
	public function patchFolderCollection(
		int $id,
		?string $title = null,
		?string $description = null,
	): DataResponse {
		$folderCollection = $this->callService(fn () => $this->folderCollectionService->findFolderCollectionEntity($id));
		$params = $this->request->getParams();

		// drop NC-added "_route" param if exists
		unset($params['_route']);
		// collectionType cannot be changed
		unset($params['collectionType']);

		/** @var string $field
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress MixedArgumentTypeCoercion
		 */
		foreach ($params as $field => $value) {
			$folderCollection->{'set' . ucfirst($field)}($value);
		}

		$updated = $this->callService(fn () => $this->folderCollectionService->updateFolderCollection($folderCollection));
		return new DataResponse($updated, Http::STATUS_OK);
	}

	/**
	 * Delete a folder collection
	 *
	 * @param int $id The ID of the folder collection to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/foldercollections/{id}')]
	public function deleteFolderCollection(int $id): DataResponse {
		$this->callService(fn () => $this->folderCollectionService->deleteFolderCollection($id));
		return new DataResponse([]);
	}

	/**
	 * Get all scores in a specific folder collection (including scores from score books)
	 *
	 * @param int $id The ID of the folder collection
	 * @param int|null $versionId Optional version ID, uses active version if not specified
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerScoreIndexed>|list<OrchestraScoresManagerScore>, array{}> the list of scores (with index for indexed collections)
	 *
	 * 200: Successful response with the list of scores
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollections/{id}/scores')]
	public function getFolderCollectionScores(int $id, ?int $versionId = null): DataResponse {
		$scores = $this->callService(fn () => $this->folderCollectionService->getScoresInFolderCollection($id, $versionId));
		return new DataResponse($scores);
	}

	/**
	 * Add a score to a folder collection
	 *
	 * Conditions:
	 * - The folder collection must exist
	 * - The score must exist
	 * - If the score is part of a score book, the score book must not be in the collection
	 *   (cannot add individual scores when their book is in the collection)
	 * - For indexed collections: index is required and must not be occupied
	 * - For alphabetical collections: index must not be provided
	 *
	 * @param int $id The ID of the folder collection
	 * @param int $scoreId The ID of the score to add
	 * @param int|null $index The index position (required for indexed collections)
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array{}, array{}> empty response
	 *
	 * 201: Successfully added score to folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/foldercollections/{id}/scores')]
	public function postFolderCollectionScore(int $id, int $scoreId, ?int $index = null): DataResponse {
		$this->callService(fn () => $this->folderCollectionService->addScoreToFolderCollection($scoreId, $id, $index));
		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Remove a score from a folder collection
	 *
	 * Conditions:
	 * - The score must be directly in the collection (not via a score book)
	 * - If the score is in the collection via a score book, the score book must be removed instead
	 *
	 * @param int $id The ID of the folder collection
	 * @param int $scoreId The ID of the score to remove
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successfully removed score from folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/foldercollections/{id}/scores/{scoreId}')]
	public function deleteFolderCollectionScore(int $id, int $scoreId): DataResponse {
		$this->callService(fn () => $this->folderCollectionService->removeScoreFromFolderCollection($scoreId, $id));
		return new DataResponse([]);
	}

	/**
	 * Get all score books in a folder collection
	 *
	 * @param int $id The ID of the folder collection
	 * @param int|null $versionId Optional version ID, uses active version if not specified
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerScoreBookIndexed>|list<OrchestraScoresManagerScoreBook>, array{}> the list of score books (with index for indexed collections)
	 *
	 * 200: Successful response with the list of score books
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollections/{id}/scorebooks')]
	public function getFolderCollectionScoreBooks(int $id, ?int $versionId = null): DataResponse {
		$scoreBooks = $this->callService(fn () => $this->folderCollectionService->getScoreBooksInFolderCollection($id, $versionId));
		return new DataResponse($scoreBooks);
	}

	/**
	 * Add a score book to a folder collection
	 *
	 * Conditions:
	 * - The folder collection must exist
	 * - The score book must exist
	 * - No score from the score book must already be individually in the collection
	 *   (cannot add book when individual scores from it are in the collection)
	 * - For indexed collections: index is required and must not be occupied
	 * - For alphabetical collections: index must not be provided
	 *
	 * @param int $id The ID of the folder collection
	 * @param int $scoreBookId The ID of the score book to add
	 * @param int|null $index The index position (required for indexed collections)
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array{}, array{}> empty response
	 *
	 * 201: Successfully added score book to folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/foldercollections/{id}/scorebooks')]
	public function postFolderCollectionScoreBook(int $id, int $scoreBookId, ?int $index = null): DataResponse {
		$this->callService(fn () => $this->folderCollectionService->addScoreBookToFolderCollection($scoreBookId, $id, $index));
		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Remove a score book from a folder collection
	 *
	 * @param int $id The ID of the folder collection
	 * @param int $scoreBookId The ID of the score book to remove
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successfully removed score book from folder collection
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/foldercollections/{id}/scorebooks/{scoreBookId}')]
	public function deleteFolderCollectionScoreBook(int $id, int $scoreBookId): DataResponse {
		$this->callService(fn () => $this->folderCollectionService->removeScoreBookFromFolderCollection($scoreBookId, $id));
		return new DataResponse([]);
	}

	/**
	 * Get all versions of a folder collection
	 *
	 * @param int $id The ID of the folder collection
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerFolderCollectionVersion>, array{}> the list of versions
	 *
	 * 200: Successful response with the list of versions
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollections/{id}/versions')]
	public function getFolderCollectionVersions(int $id): DataResponse {
		$versions = $this->callService(fn () => $this->versionService->getVersionsForFolderCollection($id));
		return new DataResponse($versions);
	}

	/**
	 * Create a new version for a folder collection
	 *
	 * @param int $id The ID of the folder collection
	 * @param string $validFrom The start date of the version (Y-m-d format)
	 * @param string|null $validTo The end date of the version (Y-m-d format) or null for active version
	 * @param int|null $copyFromVersionId Optional version ID to copy scores/scorebooks from
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerFolderCollectionVersion, array{}> the created version
	 *
	 * 201: Successful creation of the version
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/foldercollections/{id}/versions')]
	public function postFolderCollectionVersion(
		int $id,
		string $validFrom,
		?string $validTo = null,
		?int $copyFromVersionId = null,
	): DataResponse {
		$version = $this->callService(fn () => $this->versionService->createVersion($id, $validFrom, $validTo, $copyFromVersionId));
		return new DataResponse($version, Http::STATUS_CREATED);
	}

	/**
	 * Start a new version (deactivates current version, creates new one starting on specified date)
	 *
	 * @param int $id The ID of the folder collection
	 * @param string|null $validFrom The start date for the new version (Y-m-d format), defaults to today
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerFolderCollectionVersion, array{}> the created version
	 *
	 * 201: Successful creation of the new version
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/foldercollections/{id}/versions/new')]
	public function startNewVersion(int $id, ?string $validFrom = null): DataResponse {
		$version = $this->callService(fn () => $this->versionService->startNewVersion($id, $validFrom));
		return new DataResponse($version, Http::STATUS_CREATED);
	}
}
