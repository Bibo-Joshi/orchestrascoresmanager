<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\SetlistService;
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
 * @psalm-import-type OrchestraScoresManagerSetlist from ResponseDefinitions
 * @psalm-import-type OrchestraScoresManagerSetlistEntry from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by Nextcloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class SetlistApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SetlistService $setlistService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return all setlists with optional filtering
	 *
	 * @param 'all'|'future'|'past' $filter Filter by date: 'all', 'future', or 'past'
	 * @param bool|null $isDraft Filter by draft status
	 * @param bool|null $isPublished Filter by published status
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerSetlist>, array{}> the list of setlists
	 *
	 * 200: Successful response with the list of setlists
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlists')]
	public function getSetlists(string $filter = 'all', ?bool $isDraft = null, ?bool $isPublished = null): DataResponse {
		$setlists = $this->callService(fn () => $this->setlistService->getSetlists($filter, $isDraft, $isPublished));
		return new DataResponse($setlists);
	}

	/**
	 * Get a specific setlist by ID
	 *
	 * @param int $id The ID of the setlist
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlist, array{}> the setlist
	 *
	 * 200: Successful response with the setlist
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlists/{id}')]
	public function getSetlist(int $id): DataResponse {
		$setlist = $this->callService(fn () => $this->setlistService->getSetlistById($id));
		return new DataResponse($setlist);
	}

	/**
	 * Create a new setlist
	 *
	 * @param string $title The title of the setlist
	 * @param string|null $description The description of the setlist
	 * @param string|null $startDateTime The start date and time (ISO 8601 format)
	 * @param int|null $duration The duration in seconds
	 * @param int|null $defaultModerationDuration The default moderation duration in seconds
	 * @param int|null $folderCollectionVersionId The ID of the folder collection version
	 * @param bool $isDraft Whether the setlist is a draft
	 * @param bool $isPublished Whether the setlist is published
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlist, array{}> the created setlist
	 *
	 * 201: Successful creation of the setlist
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlists')]
	public function postSetlist(
		string $title,
		?string $description = null,
		?string $startDateTime = null,
		?int $duration = null,
		?int $defaultModerationDuration = null,
		?int $folderCollectionVersionId = null,
		bool $isDraft = false,
		bool $isPublished = false,
	): DataResponse {
		$setlist = new Setlist();
		$setlist->setTitle($title);
		$setlist->setDescription($description);
		if ($startDateTime !== null) {
			$setlist->setStartDateTime(new \DateTimeImmutable($startDateTime));
		}
		if ($duration !== null) {
			$setlist->setDuration($duration);
		}
		if ($defaultModerationDuration !== null) {
			$setlist->setDefaultModerationDuration($defaultModerationDuration);
		}
		$setlist->setFolderCollectionVersionId($folderCollectionVersionId);
		$setlist->setIsDraft($isDraft);
		$setlist->setIsPublished($isPublished);

		$created = $this->callService(fn () => $this->setlistService->createSetlist($setlist));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Update an existing setlist
	 *
	 * @param int $id The ID of the setlist to update
	 * @param string|null $title The title of the setlist
	 * @param string|null $description The description of the setlist
	 * @param string|null $startDateTime The start date and time (ISO 8601 format)
	 * @param int|null $duration The duration in seconds
	 * @param int|null $defaultModerationDuration The default moderation duration in seconds
	 * @param int|null $folderCollectionVersionId The ID of the folder collection version
	 * @param bool|null $isDraft Whether the setlist is a draft
	 * @param bool|null $isPublished Whether the setlist is published
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlist, array{}> the updated setlist
	 *
	 * 200: Successful update of the setlist
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 *
	 * @psalm-suppress PossiblyUnusedParam - extracted from request params
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/setlists/{id}')]
	public function patchSetlist(
		int $id,
		?string $title = null,
		?string $description = null,
		?string $startDateTime = null,
		?int $duration = null,
		?int $defaultModerationDuration = null,
		?int $folderCollectionVersionId = null,
		?bool $isDraft = null,
		?bool $isPublished = null,
	): DataResponse {
		$setlist = $this->callService(fn () => $this->setlistService->findSetlistEntity($id));
		$params = $this->request->getParams();

		// drop NC-added "_route" param if exists
		unset($params['_route']);

		/** @var string $field
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress MixedArgumentTypeCoercion
		 */
		foreach ($params as $field => $value) {
			if ($field === 'id') {
				continue;
			}
			if ($field === 'startDateTime' && is_string($value)) {
				$setlist->setStartDateTime(new \DateTimeImmutable($value));
			} else {
				$setter = 'set' . ucfirst($field);
				$setlist->$setter($value);
			}
		}

		$updated = $this->callService(fn () => $this->setlistService->updateSetlist($setlist));
		return new DataResponse($updated, Http::STATUS_OK);
	}

	/**
	 * Clone an existing setlist with a new title
	 *
	 * @param int $id The ID of the setlist to clone
	 * @param string $title The title for the cloned setlist
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlist, array{}> the cloned setlist
	 *
	 * 201: Successful cloning of the setlist
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlists/{id}/clone')]
	public function postCloneSetlist(int $id, string $title): DataResponse {
		$cloned = $this->callService(fn () => $this->setlistService->cloneSetlist($id, $title));
		return new DataResponse($cloned, Http::STATUS_CREATED);
	}

	/**
	 * Delete a setlist
	 *
	 * @param int $id The ID of the setlist to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the setlist
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/setlists/{id}')]
	public function deleteSetlist(int $id): DataResponse {
		$this->callService(fn () => $this->setlistService->deleteSetlist($id));
		return new DataResponse([]);
	}

	/**
	 * Get all entries in a setlist
	 *
	 * @param int $id The ID of the setlist
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerSetlistEntry>, array{}> the list of entries
	 *
	 * 200: Successful response with the list of entries
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlists/{id}/entries')]
	public function getSetlistEntries(int $id): DataResponse {
		$entries = $this->callService(fn () => $this->setlistService->getSetlistEntries($id));
		return new DataResponse($entries);
	}

	/**
	 * Add a new entry to a setlist
	 *
	 * @param int $id The ID of the setlist
	 * @param int $index The index position for the entry
	 * @param string|null $comment Optional comment for the entry
	 * @param int|null $moderationDuration Moderation duration in seconds
	 * @param int|null $breakDuration Break duration in seconds (for break entries)
	 * @param int|null $scoreId The ID of the score (for score entries)
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlistEntry, array{}> the created entry
	 *
	 * 201: Successfully created setlist entry
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlists/{id}/entries')]
	public function postSetlistEntry(
		int $id,
		int $index,
		?string $comment = null,
		?int $moderationDuration = null,
		?int $breakDuration = null,
		?int $scoreId = null,
	): DataResponse {
		$entry = new SetlistEntry();
		$entry->setIndex($index);
		$entry->setComment($comment);
		$entry->setModerationDuration($moderationDuration);
		$entry->setBreakDuration($breakDuration);
		$entry->setScoreId($scoreId);

		$created = $this->callService(fn () => $this->setlistService->createSetlistEntry($id, $entry));
		return new DataResponse($created, Http::STATUS_CREATED);
	}
}
