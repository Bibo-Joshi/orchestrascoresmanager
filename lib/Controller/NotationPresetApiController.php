<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\NotationPresetService;
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
 * @psalm-import-type OrchestraScoresManagerNotationPreset from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class NotationPresetApiController extends OCSController {
	use ServiceExceptionBridgeTrait;
	use NotationInputValidationTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly NotationPresetService $notationPresetService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all notation presets
	 *
	 * @return DataResponse<Http::STATUS_OK, array{notationPresets: list<OrchestraScoresManagerNotationPreset>}, array{}> all notation presets
	 *
	 * 200: Successful response with the list of notation presets
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/notation-presets')]
	public function getNotationPresets(): DataResponse {
		$presets = $this->callService(fn () => $this->notationPresetService->getAllNotationPresets());
		return new DataResponse(['notationPresets' => $presets]);
	}

	/**
	 * Create a notation preset
	 *
	 * @param int $clefId The ID of the clef of the preset
	 * @param array{diatonic: int, chromatic: int, octave: int} $transposition The transposition of the preset
	 * @param string $title The title of the preset
	 * @param string|null $description Optional description of the preset
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerNotationPreset, array{}> the created notation preset
	 *
	 * 201: Successful creation of the notation preset
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/notation-presets')]
	public function postNotationPreset(int $clefId, array $transposition, string $title, ?string $description = null): DataResponse {
		$this->assertTranspositionTypes($transposition);

		$created = $this->callService(
			fn () => $this->notationPresetService->createNotationPreset($clefId, $transposition, $title, $description)
		);
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Get a notation preset
	 *
	 * @param int $id The ID of the notation preset
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerNotationPreset, array{}> the notation preset
	 *
	 * 200: Successful response with the notation preset
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/notation-presets/{id}')]
	public function getNotationPreset(int $id): DataResponse {
		$preset = $this->callService(fn () => $this->notationPresetService->getNotationPresetById($id));
		return new DataResponse($preset);
	}

	/**
	 * Update a notation preset
	 *
	 * Part variants linked to the preset that no longer match its clef and transposition are unlinked.
	 *
	 * @param int $id The ID of the notation preset to update
	 * @param int|null $clefId The ID of the clef of the preset
	 * @param array{diatonic: int, chromatic: int, octave: int}|null $transposition The transposition of the preset
	 * @param string|null $title The title of the preset
	 * @param string|null $description The description of the preset, an empty string removes it
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerNotationPreset, array{}> the updated notation preset
	 *
	 * 200: Successful update of the notation preset
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/notation-presets/{id}')]
	public function patchNotationPreset(
		int $id,
		?int $clefId = null,
		?array $transposition = null,
		?string $title = null,
		?string $description = null,
	): DataResponse {
		$this->assertTranspositionTypes($transposition);

		$updated = $this->callService(function () use ($id, $clefId, $transposition, $title, $description) {
			$preset = $this->notationPresetService->findNotationPresetEntity($id);
			return $this->notationPresetService->updateNotationPreset($preset, $clefId, $transposition, $title, $description);
		});
		return new DataResponse($updated);
	}

	/**
	 * Delete a notation preset
	 *
	 * Part variants using the preset keep their clef and transposition and are unlinked from the preset.
	 *
	 * @param int $id The ID of the notation preset to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the notation preset
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/notation-presets/{id}')]
	public function deleteNotationPreset(int $id): DataResponse {
		$this->callService(fn () => $this->notationPresetService->deleteNotationPreset($id));
		return new DataResponse([]);
	}
}
