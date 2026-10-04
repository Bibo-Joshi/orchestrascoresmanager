<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\TagService;
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
 * @psalm-import-type OrchestraScoresManagerTag from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class TagApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly TagMapper $tagMapper,
		private readonly TagService $tagService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return all available tags
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerTag>, array{}> the list of tags
	 *
	 * 200: Successful response with the list of tags
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/tags')]
	public function getTags(): DataResponse {
		$tags = $this->callService(fn () => $this->tagService->getAllTags());
		return new DataResponse($tags);
	}

	/**
	 * Create a new tag
	 *
	 * @param string $name The name of the new tag
	 *
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerTag, array{}> the created tag
	 *
	 * 201: Successful creation of the tag
	 * 403: Forbidden
	 *
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/tags')]
	public function postTag(string $name): DataResponse {
		// delegate authorization + creation to TagService and translate service exceptions
		$created = $this->callService(fn () => $this->tagService->createTag($name));
		return new DataResponse($created, 201);
	}
}
