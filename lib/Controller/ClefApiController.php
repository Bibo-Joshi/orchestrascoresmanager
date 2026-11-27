<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\ClefService;
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
 * @psalm-import-type OrchestraScoresManagerClef from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ClefApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ClefService $clefService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all clefs
	 *
	 * @return DataResponse<Http::STATUS_OK, array{clefs: list<OrchestraScoresManagerClef>}, array{}> all clefs
	 *
	 * 200: Successful response with the list of clefs
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/clefs')]
	public function getClefs(): DataResponse {
		$clefs = $this->callService(fn () => $this->clefService->getAllClefs());
		return new DataResponse(['clefs' => $clefs]);
	}

	/**
	 * Create a clef
	 *
	 * If an identical clef already exists, it is returned instead of creating a duplicate.
	 *
	 * @param string $sign The clef sign: `G`, `C`, `F`, `percussion` or `TAB`
	 * @param int|null $line The staff line (1-5), required for `G`, `C` and `F`, not allowed otherwise
	 * @param int $octave The octave shift
	 * @param string|null $title Optional title of the clef
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerClef, array{}> the created or already existing clef
	 *
	 * 201: Successful creation of the clef
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/clefs')]
	public function postClef(string $sign, ?int $line = null, int $octave = 0, ?string $title = null): DataResponse {
		$clef = $this->callService(fn () => $this->clefService->findOrCreateClef($sign, $line, $octave, $title));
		return new DataResponse($clef, Http::STATUS_CREATED);
	}

	/**
	 * Get a clef
	 *
	 * @param int $id The ID of the clef
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerClef, array{}> the clef
	 *
	 * 200: Successful response with the clef
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/clefs/{id}')]
	public function getClef(int $id): DataResponse {
		$clef = $this->callService(fn () => $this->clefService->getClefById($id));
		return new DataResponse($clef);
	}

	/**
	 * Update a clef
	 *
	 * @param int $id The ID of the clef to update
	 * @param string|null $sign The clef sign: `G`, `C`, `F`, `percussion` or `TAB`
	 * @param int|null $line The staff line (1-5)
	 * @param int|null $octave The octave shift
	 * @param string|null $title The title of the clef, an empty string removes it
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerClef, array{}> the updated clef
	 *
	 * 200: Successful update of the clef
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/clefs/{id}')]
	public function patchClef(int $id, ?string $sign = null, ?int $line = null, ?int $octave = null, ?string $title = null): DataResponse {
		$updated = $this->callService(function () use ($id, $sign, $line, $octave, $title) {
			$clef = $this->clefService->findClefEntity($id);
			return $this->clefService->updateClef($clef, $sign, $line, $octave, $title);
		});
		return new DataResponse($updated);
	}

	/**
	 * Delete a clef that is not in use
	 *
	 * @param int $id The ID of the clef to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the clef
	 * 400: Bad request, e.g. because the clef is still in use
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/clefs/{id}')]
	public function deleteClef(int $id): DataResponse {
		$this->callService(fn () => $this->clefService->deleteClef($id));
		return new DataResponse([]);
	}
}
