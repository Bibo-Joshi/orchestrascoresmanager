<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\SetlistTemplateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type OrchestraScoresManagerSetlistTemplate from ResponseDefinitions
 * @psalm-suppress UnusedClass - discovered by Nextcloud
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class SetlistTemplateApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SetlistTemplateService $templateService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerSetlistTemplate>, array{}>
	 * 200: Shared PDF templates
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlist-templates')]
	public function getTemplates(): DataResponse {
		return new DataResponse($this->callService(fn () => $this->templateService->getTemplates()));
	}

	/**
	 * @param array $template Template fields excluding generated ID and timestamps
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlistTemplate, array{}>
	 * 201: Created template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlist-templates')]
	public function postTemplate(array $template): DataResponse {
		return new DataResponse($this->callService(fn () => $this->templateService->createTemplate($template)), Http::STATUS_CREATED);
	}

	/**
	 * @param string $id Stable template ID
	 * @param array $template Partial template fields
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlistTemplate, array{}>
	 * 200: Updated template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/setlist-templates/{id}')]
	public function patchTemplate(string $id, array $template): DataResponse {
		return new DataResponse($this->callService(fn () => $this->templateService->patchTemplate($id, $template)));
	}

	/**
	 * @param string $id Stable template ID
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 * 200: Deleted template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/setlist-templates/{id}')]
	public function deleteTemplate(string $id): DataResponse {
		$this->callService(fn () => $this->templateService->deleteTemplate($id));
		return new DataResponse([]);
	}
}
