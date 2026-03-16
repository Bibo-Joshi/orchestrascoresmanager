<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\FolderCollectionVersionService;
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
 * Controller for folder collection version operations.
 *
 * Provides endpoints to access and modify folder collection versions
 * independently of their parent folder collection.
 *
 * @psalm-import-type OrchestraScoresManagerFolderCollectionVersion from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class FolderCollectionVersionApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly FolderCollectionVersionService $versionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a folder collection version by ID
	 *
	 * @param int $id The ID of the version
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerFolderCollectionVersion, array{}> the version
	 *
	 * 200: Successful response with the version
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/foldercollectionversions/{id}')]
	public function getFolderCollectionVersion(int $id): DataResponse {
		$version = $this->callService(fn () => $this->versionService->getVersionById($id));
		return new DataResponse($version);
	}

	/**
	 * Update a folder collection version
	 *
	 * Only valid_to can be set for active versions to deactivate them.
	 * Non-active versions cannot be modified.
	 *
	 * @param int $id The ID of the version to update
	 * @param string|null $validTo The new end date (Y-m-d format) to deactivate the version
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerFolderCollectionVersion, array{}> the updated version
	 *
	 * 200: Successful update of the version
	 * @throws OCSBadRequestException Invalid request parameters
	 * @throws OCSForbiddenException Insufficient permissions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/foldercollectionversions/{id}')]
	public function patchFolderCollectionVersion(int $id, ?string $validTo = null): DataResponse {
		$version = $this->callService(fn () => $this->versionService->updateVersion($id, $validTo));
		return new DataResponse($version);
	}
}
