<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\ConductorsScoreVariantService;
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
 * @psalm-import-type OrchestraScoresManagerConductorsScoreVariant from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ConductorsScoreVariantApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ConductorsScoreVariantService $conductorsScoreVariantService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all variants of a specific conductor's score
	 *
	 * @param int $id The ID of the conductor's score
	 *
	 * @return DataResponse<Http::STATUS_OK, array{variants: list<OrchestraScoresManagerConductorsScoreVariant>}, array{}> the variants of the conductor's score
	 *
	 * 200: Successful response with the list of variants
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/conductors-scores/{id}/variants')]
	public function getConductorsScoreVariants(int $id): DataResponse {
		$variants = $this->callService(fn () => $this->conductorsScoreVariantService->getVariantsForConductorsScore($id));
		return new DataResponse(['variants' => $variants]);
	}

	/**
	 * Add a variant to a specific conductor's score
	 *
	 * @param int $id The ID of the conductor's score
	 * @param string $type The type of the variant, `full` or `condensed`
	 * @param string|null $description Optional description of the variant
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerConductorsScoreVariant, array{}> the created variant
	 *
	 * 201: Successful creation of the variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/conductors-scores/{id}/variants')]
	public function postConductorsScoreVariant(int $id, string $type, ?string $description = null): DataResponse {
		$created = $this->callService(
			fn () => $this->conductorsScoreVariantService->createConductorsScoreVariant($id, $type, $description)
		);
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Get a conductor's score variant
	 *
	 * @param int $id The ID of the variant
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerConductorsScoreVariant, array{}> the variant
	 *
	 * 200: Successful response with the variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/conductors-score-variants/{id}')]
	public function getConductorsScoreVariant(int $id): DataResponse {
		$variant = $this->callService(fn () => $this->conductorsScoreVariantService->getConductorsScoreVariantById($id));
		return new DataResponse($variant);
	}

	/**
	 * Update a conductor's score variant
	 *
	 * @param int $id The ID of the variant to update
	 * @param string|null $type The type of the variant, `full` or `condensed`
	 * @param string|null $description The description of the variant, an empty string removes it
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerConductorsScoreVariant, array{}> the updated variant
	 *
	 * 200: Successful update of the variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/conductors-score-variants/{id}')]
	public function patchConductorsScoreVariant(int $id, ?string $type = null, ?string $description = null): DataResponse {
		$updated = $this->callService(function () use ($id, $type, $description) {
			$variant = $this->conductorsScoreVariantService->findConductorsScoreVariantEntity($id);
			return $this->conductorsScoreVariantService->updateConductorsScoreVariant($variant, $type, $description);
		});
		return new DataResponse($updated);
	}

	/**
	 * Delete a conductor's score variant
	 *
	 * @param int $id The ID of the variant to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/conductors-score-variants/{id}')]
	public function deleteConductorsScoreVariant(int $id): DataResponse {
		$this->callService(fn () => $this->conductorsScoreVariantService->deleteConductorsScoreVariant($id));
		return new DataResponse([]);
	}
}
