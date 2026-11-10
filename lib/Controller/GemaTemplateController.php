<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

/**
 * Serves the GEMA report template through Nextcloud routing.
 */
class GemaTemplateController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/gema-template')]

    public function getTemplate(): Response {
        $path = __DIR__ . '/../../public/gema-template.xlsx';

        if (!is_file($path) || !is_readable($path)) {
            return new NotFoundResponse();
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return new NotFoundResponse();
        }

        return new DataDisplayResponse(
            $content,
            Http::STATUS_OK,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'inline; filename="gema-template.xlsx"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
