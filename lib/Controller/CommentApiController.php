<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCA\OrchestraScoresManager\ResponseDefinitions;
use OCA\OrchestraScoresManager\Service\CommentService;
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
 * @psalm-import-type OrchestraScoresManagerComment from ResponseDefinitions
 * @psalm-suppress UnusedClass - Controller discovered by NextCloud framework
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class CommentApiController extends OCSController {
	use ServiceExceptionBridgeTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CommentService $commentService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a specific comment by ID
	 *
	 * @param int $id The ID of the comment
	 *
	 * @return DataResponse<Http::STATUS_OK, OrchestraScoresManagerComment, array{}> the comment
	 *
	 * 200: Successful response with the comment
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/comments/{id}')]
	public function getComment(int $id): DataResponse {
		$comment = $this->callService(fn () => $this->commentService->getCommentById($id));
		return new DataResponse($comment);
	}

	/**
	 * Delete a comment
	 *
	 * @param int $id The ID of the comment to delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}> empty response
	 *
	 * 200: Successful deletion of the comment
	 * 400: Bad request
	 * 403: Forbidden
	 * @throws OCSBadRequestException if the request is invalid
	 * @throws OCSForbiddenException if the user does not have permission
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/comments/{id}')]
	public function deleteComment(int $id): DataResponse {
		$this->callService(fn () => $this->commentService->deleteComment($id));
		return new DataResponse([]);
	}
}
