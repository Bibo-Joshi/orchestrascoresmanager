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
		return new DataResponse($templates);
	}

	/**
	 * Create a shared PDF template
	 *
	 * @param array{name:string,description?:string|null,headerInfo?:array{entries:list<'date'|'startTime'|'endTime'|'playDuration'|'breaksDuration'|'overallDuration'|'folderCollection'>,layout:'table'|'subtitle'},fontSizes?:array{title?:float,subtitle?:float,colorLegend?:float,columnTitle?:float,scoreRow?:float,breakRow?:float}|null,margin:float,cellPadding:float,rules:list<array{id:string,order:int,label?:string|null,expression:array<string,mixed>,backgroundColor?:string|null,cellPadding?:float|null}>} $template Template fields
	 * @return DataResponse<Http::STATUS_CREATED, OrchestraScoresManagerSetlistTemplate, array{}>
	 *
	 * 201: Created template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/setlist-templates')]
	public function postTemplate(array $template): DataResponse {
		$created = $this->callService(fn () => $this->templateService->createTemplate($template));
		return new DataResponse($created, Http::STATUS_CREATED);
	}

	/**
	 * Update a shared PDF template
	 *
	 * @param int $id Stable template ID
	 * @param array{name?:string,description?:string|null,headerInfo?:array{entries:list<'date'|'startTime'|'endTime'|'playDuration'|'breaksDuration'|'overallDuration'|'folderCollection'>,layout:'table'|'subtitle'},fontSizes?:array{title?:float,subtitle?:float,colorLegend?:float,columnTitle?:float,scoreRow?:float,breakRow?:float}|null,margin?:float,cellPadding?:float,rules?:list<array{id:string,order:int,label?:string|null,expression:array<string,mixed>,backgroundColor?:string|null,cellPadding?:float|null}>} $template Partial template fields
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerSetlistTemplate, array{}>
	 *
	 * 200: Updated template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/setlist-templates/{id}')]
	public function patchTemplate(int $id, array $template): DataResponse {
		$updated = $this->callService(fn () => $this->templateService->patchTemplate($id, $template));
		return new DataResponse($updated);
	}

	/**
	 * Delete a shared PDF template
	 *
	 * @param int $id Stable template ID
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Deleted template
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/setlist-templates/{id}')]
	public function deleteTemplate(int $id): DataResponse {
		$this->callService(fn () => $this->templateService->deleteTemplate($id));
		return new DataResponse([]);
	}

}
