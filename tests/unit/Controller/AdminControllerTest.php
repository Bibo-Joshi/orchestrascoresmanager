<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\AdminController;
use OCA\OrchestraScoresManager\Service\ConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminController.
 */
final class AdminControllerTest extends TestCase {
	private ConfigService $configService;
	private IRequest $request;
	private AdminController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new AdminController(
			'orchestrascoresmanager',
			$this->request,
			$this->configService
		);
	}

	#[TestWith([['admin', 'editors', 'orchestra']])]
	#[TestWith([[]])]
	public function testGetEditGroupsReturnsSettings(array $groups): void {
		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn($groups);

		$response = $this->controller->getEditGroups();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(['editGroups' => $groups], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith([['admin', 'musicians']])]
	#[TestWith([[]])]
	public function testPostEditGroupsUpdatesSettings(array $groups): void {
		$this->configService->expects($this->once())
			->method('setAllowedEditGroups')
			->with($groups);

		$this->configService->expects($this->once())
			->method('getAllowedEditGroups')
			->willReturn($groups);

		$response = $this->controller->postEditGroups($groups);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(['editGroups' => $groups], $response->getData());
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}
}
