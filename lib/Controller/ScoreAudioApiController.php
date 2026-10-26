<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\ScoreAudioService;
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
 * @psalm-import-type OrchestraScoresManagerScoreAudio from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ScoreAudioApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ScoreAudioService $scoreAudioService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all audio examples for a specific score
	 *
	 * @param int $id The ID of the score
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerScoreAudio>, array{}> the list of audio examples
	 *
	 * 200: Successful response with the list of audio examples
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/scores/{id}/audio-examples')]
	public function getScoreAudioExamples(int $id): DataResponse {
		$audios = $this->callService(fn () => $this->scoreAudioService->getAudioExamplesForScore($id));
		return new DataResponse($audios);
	}

	/**
	 * Add an audio example to a specific score
	 *
	 * Either `link` (generic example) or `provider` together with `providerId` (provider-specific example) must be given.
	 *
	 * @param int $id The ID of the score
	 * @param string|null $provider The provider of the audio example, e.g. 'youtube'. Must be null for generic examples
	 * @param string|null $providerId The provider-specific identifier. Must be null for generic examples
	 * @param string|null $link The external URL for generic examples. Must be null for provider-specific examples
	 * @param string|null $comment Optional description of the audio example
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerScoreAudio, array{}> the created audio example
	 *
	 * 201: Successful creation of the audio example
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/scores/{id}/audio-examples')]
	public function postScoreAudioExample(
		int $id,
		?string $provider = null,
		?string $providerId = null,
		?string $link = null,
		?string $comment = null,
	): DataResponse {
		$created = $this->callService(
			fn () => $this->scoreAudioService->createAudioExample($id, $provider, $providerId, $link, $comment)
		);
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Delete an audio example
	 *
	 * @param int $id The ID of the audio example to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the audio example
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/audio-examples/{id}')]
	public function deleteAudioExample(int $id): DataResponse {
		$this->callService(fn () => $this->scoreAudioService->deleteAudioExample($id));
		return new DataResponse([]);
	}
}
