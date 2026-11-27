<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\PartService;
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
 * @psalm-import-type OrchestraScoresManagerPart from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class PartApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly PartService $partService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all parts of a specific score
	 *
	 * @param int $id The ID of the score
	 *
	 * @return DataResponse<Http::STATUS_OK, array{parts: list<OrchestraScoresManagerPart>}, array{}> the parts of the score
	 *
	 * 200: Successful response with the list of parts
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scores/{id}/parts')]
	public function getScoreParts(int $id): DataResponse {
		$parts = $this->callService(fn () => $this->partService->getPartsForScore($id));
		return new DataResponse(['parts' => $parts]);
	}

	/**
	 * Add a part to a specific score
	 *
	 * @param int $id The ID of the score
	 * @param string $title The title of the part
	 * @param string|null $description Optional description of the part
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerPart, array{}> the created part
	 *
	 * 201: Successful creation of the part
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scores/{id}/parts')]
	public function postScorePart(int $id, string $title, ?string $description = null): DataResponse {
		$created = $this->callService(fn () => $this->partService->createPart($id, $title, $description));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Get a part
	 *
	 * @param int $id The ID of the part
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerPart, array{}> the part
	 *
	 * 200: Successful response with the part
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/parts/{id}')]
	public function getPart(int $id): DataResponse {
		$part = $this->callService(fn () => $this->partService->getPartById($id));
		return new DataResponse($part);
	}

	/**
	 * Update a part
	 *
	 * @param int $id The ID of the part to update
	 * @param string|null $title The title of the part
	 * @param string|null $description The description of the part, an empty string removes it
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerPart, array{}> the updated part
	 *
	 * 200: Successful update of the part
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/parts/{id}')]
	public function patchPart(int $id, ?string $title = null, ?string $description = null): DataResponse {
		$updated = $this->callService(function () use ($id, $title, $description) {
			$part = $this->partService->findPartEntity($id);
			return $this->partService->updatePart($part, $title, $description);
		});
		return new DataResponse($updated);
	}

	/**
	 * Delete a part together with its variants
	 *
	 * @param int $id The ID of the part to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the part
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/parts/{id}')]
	public function deletePart(int $id): DataResponse {
		$this->callService(fn () => $this->partService->deletePart($id));
		return new DataResponse([]);
	}
}
