<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\SetlistEntryService;
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
 * @psalm-import-type OrchestraScoresManagerSetlistEntry from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by Nextcloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class SetlistEntryApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SetlistEntryService $setlistEntryService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a specific setlist entry by ID
	 *
	 * @param int $id The ID of the setlist entry
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlistEntry, array{}> the entry
	 *
	 * 200: Successful response with the entry
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlistentries/{id}')]
	public function getSetlistEntry(int $id): DataResponse {
		$entry = $this->callService(fn () => $this->setlistEntryService->getSetlistEntryById($id));
		return new DataResponse($entry);
	}

	/**
	 * Update an existing setlist entry
	 *
	 * @param int $id The ID of the setlist entry to update
	 * @param int|null $index The new index position
	 * @param string|null $comment Optional comment for the entry
	 * @param int|null $moderationDuration Moderation duration in seconds
	 * @param int|null $breakDuration Break duration in seconds
	 * @param int|null $scoreId The ID of the score
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlistEntry, array{}> the updated entry
	 *
	 * 200: Successfully updated setlist entry
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 *
	 * @psalm-suppress PossiblyUnusedParam - extracted from request params
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/setlistentries/{id}')]
	public function patchSetlistEntry(
		int $id,
		?int $index = null,
		?string $comment = null,
		?int $moderationDuration = null,
		?int $breakDuration = null,
		?int $scoreId = null,
	): DataResponse {
		$entry = $this->callService(fn () => $this->setlistEntryService->findSetlistEntryEntity($id));
		$params = $this->request->getParams();

		// drop NC-added "_route" param if exists
		unset($params['_route']);
		unset($params['id']);

		/** @var string $field
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress MixedArgumentTypeCoercion
		 */
		foreach ($params as $field => $value) {
			$setter = 'set' . ucfirst($field);
			$entry->$setter($value);
		}

		$updated = $this->callService(fn () => $this->setlistEntryService->updateSetlistEntry($id, $entry));
		return new DataResponse($updated, Http::STATUS_OK);
	}

	/**
	 * Delete a setlist entry
	 *
	 * @param int $id The ID of the setlist entry to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successfully deleted setlist entry
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/setlistentries/{id}')]
	public function deleteSetlistEntry(int $id): DataResponse {
		$this->callService(fn () => $this->setlistEntryService->deleteSetlistEntry($id));
		return new DataResponse([]);
	}

	/**
	 * Batch update multiple setlist entries
	 *
	 * @param list<array{id: int, index?: int, comment?: ?string, moderationDuration?: ?int, breakDuration?: ?int, scoreId?: ?int}> $entries Array of entry data with IDs
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerSetlistEntry>, array{}> the updated entries
	 *
	 * 200: Successfully updated entries
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlistentries/batch')]
	public function postSetlistEntriesBatch(array $entries): DataResponse {
		$updated = $this->callService(fn () => $this->setlistEntryService->batchUpdateSetlistEntries($entries));
		return new DataResponse($updated, Http::STATUS_OK);
	}
}
