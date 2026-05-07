<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\UserSettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type OrchestraScoresManagerUserSetlistSettings from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class UserSettingsController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly UserSettingsService $userSettingsService,
		private readonly IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get setlist settings for the authenticated user
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerUserSetlistSettings, array{}>
	 *
	 * 200: User setlist settings
	 * 403: Not authenticated
	 * @throws OCSForbiddenException If the user is not authenticated.
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/settings/user/setlists')]
	public function getSetlistSettings(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			throw new OCSForbiddenException('Not authenticated');
		}

		$settings = $this->callService(fn () => $this->userSettingsService->getSetlistSettings($userId));
		return new DataResponse($settings);
	}

	/**
	 * Update setlist settings for the authenticated user
	 *
	 * @param int|null $defaultModerationTime    Time (e.g. 60 seconds) or null to clear.
	 * @param int|null    $defaultFolderCollectionId Folder collection ID or null to clear.
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerUserSetlistSettings, array{}>
	 *
	 * 200: Updated user setlist settings
	 * 403: Not authenticated
	 * @throws OCSForbiddenException If the user is not authenticated.
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/settings/user/setlists')]
	public function putSetlistSettings(
		?int $defaultModerationTime = null,
		?int $defaultFolderCollectionId = null,
	): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			throw new OCSForbiddenException('Not authenticated');
		}

		$this->callService(fn () => $this->userSettingsService->updateSetlistSettings(
			$userId,
			$defaultModerationTime,
			$defaultFolderCollectionId,
		));

		$settings = $this->callService(fn () => $this->userSettingsService->getSetlistSettings($userId));
		return new DataResponse($settings);
	}
}



