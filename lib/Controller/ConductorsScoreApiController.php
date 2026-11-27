<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\ConductorsScoreService;
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
 * @psalm-import-type OrchestraScoresManagerConductorsScore from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ConductorsScoreApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ConductorsScoreService $conductorsScoreService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the conductor's score of a specific score
	 *
	 * @param int $id The ID of the score
	 *
	 * @return DataResponse<Http::STATUS_OK, array{conductorsScore: OrchestraScoresManagerConductorsScore|null}, array{}> the conductor's score, null if the score has none
	 *
	 * 200: Successful response with the conductor's score
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scores/{id}/conductors-score')]
	public function getScoreConductorsScore(int $id): DataResponse {
		$conductorsScore = $this->callService(fn () => $this->conductorsScoreService->getConductorsScoreForScore($id));
		return new DataResponse(['conductorsScore' => $conductorsScore]);
	}

	/**
	 * Add a conductor's score to a specific score
	 *
	 * @param int $id The ID of the score
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerConductorsScore, array{}> the created conductor's score
	 *
	 * 201: Successful creation of the conductor's score
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scores/{id}/conductors-score')]
	public function postScoreConductorsScore(int $id): DataResponse {
		$created = $this->callService(fn () => $this->conductorsScoreService->createConductorsScore($id));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Delete a conductor's score together with its variants
	 *
	 * @param int $id The ID of the conductor's score to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the conductor's score
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/conductors-scores/{id}')]
	public function deleteConductorsScore(int $id): DataResponse {
		$this->callService(fn () => $this->conductorsScoreService->deleteConductorsScore($id));
		return new DataResponse([]);
	}
}
