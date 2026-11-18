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
	 * List shared PDF templates
	 *
	 * @return DataResponse<Http::STATUS_OK, list<OrchestraScoresManagerSetlistTemplate>, array{}>
	 *
	 * 200: Shared PDF templates
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/setlist-templates')]
	public function getTemplates(): DataResponse {
		$templates = $this->callService(fn () => $this->templateService->getTemplates());
		return new DataResponse(array_map($this->forResponse(...), $templates));
	}

	/**
	 * Create a shared PDF template
	 *
	 * @param array<string, mixed> $template Template fields excluding generated ID and timestamps
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlistTemplate, array{}>
	 *
	 * 201: Created template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlist-templates')]
	public function postTemplate(array $template): DataResponse {
		$created = $this->callService(fn () => $this->templateService->createTemplate($template));
		return new DataResponse($this->forResponse($created), Http::STATUS_CREATED);
	}

	/**
	 * Update a shared PDF template
	 *
	 * @param string $id Stable template ID
	 * @param array<string, mixed> $template Partial template fields
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlistTemplate, array{}>
	 *
	 * 200: Updated template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/setlist-templates/{id}')]
	public function patchTemplate(string $id, array $template): DataResponse {
		$updated = $this->callService(fn () => $this->templateService->patchTemplate($id, $template));
		return new DataResponse($this->forResponse($updated));
	}

	/**
	 * Delete a shared PDF template
	 *
	 * @param string $id Stable template ID
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Deleted template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/setlist-templates/{id}')]
	public function deleteTemplate(string $id): DataResponse {
		$this->callService(fn () => $this->templateService->deleteTemplate($id));
		return new DataResponse([]);
	}

	/**
	 * An empty fontSizes map must be a JSON object, not a JSON array.
	 * Keep the internal representation as a PHP array so PATCH can merge font sizes.
	 *
	 * @param array<string, mixed> $template
	 * @return array<string, mixed>
	 */
	private function forResponse(array $template): array {
		if (isset($template['fontSizes']) && is_array($template['fontSizes']) && $template['fontSizes'] === []) {
			$template['fontSizes'] = new \stdClass();
		}
		return $template;
	}
}
