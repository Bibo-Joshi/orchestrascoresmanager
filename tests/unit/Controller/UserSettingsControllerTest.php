<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\UserSettingsController;
use OCA\OrchestraScoresManager\Service\UserSettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserSettingsController.
 */
final class UserSettingsControllerTest extends TestCase {
	private UserSettingsService $userSettingsService;
	private IRequest $request;
	private IUserSession $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->userSettingsService = $this->createMock(UserSettingsService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
	}

	/**
	 * Create a controller with a user session returning the given user ID (or null).
	 */
	private function makeController(?string $userId): UserSettingsController {
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
			$this->userSession->method('getUser')->willReturn($user);
		} else {
			$this->userSession->method('getUser')->willReturn(null);
		}

		return new UserSettingsController(
			'orchestrascoresmanager',
			$this->request,
			$this->userSettingsService,
			$this->userSession,
		);
	}

	public function testGetSetlistSettingsReturnsSettings(): void {
		$controller = $this->makeController('alice');

		$expected = ['defaultModerationTime' => 5400, 'defaultFolderCollectionId' => 3];

		$this->userSettingsService->expects($this->once())
			->method('getSetlistSettings')
			->with('alice')
			->willReturn($expected);

		$response = $controller->getSetlistSettings();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expected, $response->getData());
	}

	public function testGetSetlistSettingsThrowsWhenUnauthenticated(): void {
		$controller = $this->makeController(null);

		$this->expectException(OCSForbiddenException::class);

		$controller->getSetlistSettings();
	}

	public function testPutSetlistSettingsPersistsAndReturnsSettings(): void {
		$controller = $this->makeController('alice');

		$expected = ['defaultModerationTime' => 1800, 'defaultFolderCollectionId' => null];

		$this->userSettingsService->expects($this->once())
			->method('updateSetlistSettings')
			->with('alice', 1800, null);

		$this->userSettingsService->expects($this->once())
			->method('getSetlistSettings')
			->with('alice')
			->willReturn($expected);

		$response = $controller->putSetlistSettings(1800, null);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expected, $response->getData());
	}

	public function testPutSetlistSettingsThrowsWhenUnauthenticated(): void {
		$controller = $this->makeController(null);

		$this->expectException(OCSForbiddenException::class);

		$controller->putSetlistSettings(3600, 5);
	}

	public function testPutSetlistSettingsWithNullsClears(): void {
		$controller = $this->makeController('bob');

		$expected = ['defaultModerationTime' => null, 'defaultFolderCollectionId' => null];

		$this->userSettingsService->expects($this->once())
			->method('updateSetlistSettings')
			->with('bob', null, null);

		$this->userSettingsService->expects($this->once())
			->method('getSetlistSettings')
			->with('bob')
			->willReturn($expected);

		$response = $controller->putSetlistSettings(null, null);

		$this->assertSame($expected, $response->getData());
	}
}
