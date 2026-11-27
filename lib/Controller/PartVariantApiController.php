<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\PartVariantService;
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
 * @psalm-import-type OrchestraScoresManagerPartVariant from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class PartVariantApiController extends OCSController {
	use ServiceExceptionBridgeTrait;
	use NotationInputValidationTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly PartVariantService $partVariantService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all variants of a specific part
	 *
	 * @param int $id The ID of the part
	 *
	 * @return DataResponse<Http::STATUS_OK, array{variants: list<OrchestraScoresManagerPartVariant>}, array{}> the variants of the part
	 *
	 * 200: Successful response with the list of part variants
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/parts/{id}/variants')]
	public function getPartVariants(int $id): DataResponse {
		$variants = $this->callService(fn () => $this->partVariantService->getVariantsForPart($id));
		return new DataResponse(['variants' => $variants]);
	}

	/**
	 * Add a variant to a specific part
	 *
	 * The notation is given either by `notationPresetId` or by `clef` together with `transposition`.
	 * A clef is either `{id}` of an existing clef or `{sign, line, octave, title}`, which finds or creates the clef.
	 *
	 * @param int $id The ID of the part
	 * @param int|null $notationPresetId The ID of the notation preset to use
	 * @param array{id: int}|array{sign: string, line?: int|null, octave?: int, title?: string|null}|null $clef The clef of the variant
	 * @param array{diatonic: int, chromatic: int, octave: int}|null $transposition The transposition of the variant
	 * @param string|null $description Optional description of the variant
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerPartVariant, array{}> the created part variant
	 *
	 * 201: Successful creation of the part variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/parts/{id}/variants')]
	public function postPartVariant(
		int $id,
		?int $notationPresetId = null,
		?array $clef = null,
		?array $transposition = null,
		?string $description = null,
	): DataResponse {
		$this->assertClefTypes($clef);
		$this->assertTranspositionTypes($transposition);

		$created = $this->callService(
			fn () => $this->partVariantService->createPartVariant($id, $notationPresetId, $clef, $transposition, $description)
		);
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Get a part variant
	 *
	 * @param int $id The ID of the part variant
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerPartVariant, array{}> the part variant
	 *
	 * 200: Successful response with the part variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/part-variants/{id}')]
	public function getPartVariant(int $id): DataResponse {
		$variant = $this->callService(fn () => $this->partVariantService->getPartVariantById($id));
		return new DataResponse($variant);
	}

	/**
	 * Update a part variant
	 *
	 * Either `notationPresetId` or `clef` and/or `transposition` can be given to change the notation.
	 *
	 * @param int $id The ID of the part variant to update
	 * @param int|null $notationPresetId The ID of the notation preset to use
	 * @param array{id: int}|array{sign: string, line?: int|null, octave?: int, title?: string|null}|null $clef The new clef of the variant
	 * @param array{diatonic: int, chromatic: int, octave: int}|null $transposition The new transposition of the variant
	 * @param string|null $description The description of the variant, an empty string removes it
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerPartVariant, array{}> the updated part variant
	 *
	 * 200: Successful update of the part variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/part-variants/{id}')]
	public function patchPartVariant(
		int $id,
		?int $notationPresetId = null,
		?array $clef = null,
		?array $transposition = null,
		?string $description = null,
	): DataResponse {
		$this->assertClefTypes($clef);
		$this->assertTranspositionTypes($transposition);

		$updated = $this->callService(function () use ($id, $notationPresetId, $clef, $transposition, $description) {
			$variant = $this->partVariantService->findPartVariantEntity($id);
			return $this->partVariantService->updatePartVariant($variant, $notationPresetId, $clef, $transposition, $description);
		});
		return new DataResponse($updated);
	}

	/**
	 * Delete a part variant
	 *
	 * @param int $id The ID of the part variant to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the part variant
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/part-variants/{id}')]
	public function deletePartVariant(int $id): DataResponse {
		$this->callService(fn () => $this->partVariantService->deletePartVariant($id));
		return new DataResponse([]);
	}
}
