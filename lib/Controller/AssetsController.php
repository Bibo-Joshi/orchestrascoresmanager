<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;

/**
 * Serves application assets through Nextcloud routing.
 *
 * @psalm-suppress UnusedClass - Controller discovered by Nextcloud framework
 */
class AssetsController extends Controller {
	private const CACHE_SECONDS = 3600;

	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	protected function getGemaTemplatePath(): string {
		return __DIR__ . '/../../public/gema-template.xlsx';
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/assets/gema-template')]
	public function getGemaTemplate(): Response {
		$path = $this->getGemaTemplatePath();

		if (!is_file($path) || !is_readable($path)) {
			return new NotFoundResponse();
		}

		$response = new StreamResponse(
			$path,
			Http::STATUS_OK,
			[
				'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'X-Content-Type-Options' => 'nosniff',
			],
		);

		$response->cacheFor(self::CACHE_SECONDS);

		return $response;
	}
}
