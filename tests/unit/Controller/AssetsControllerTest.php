<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\AssetsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AssetsControllerTest extends TestCase {
	private AssetsController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = new AssetsController(
			'orchestrascoresmanager',
			$this->createMock(IRequest::class),
		);
	}

	public function testGemaTemplateResponse(): void {
		$response = $this->controller->getGemaTemplate();

		$this->assertInstanceOf(StreamResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());

		$headers = $response->getHeaders();

		$this->assertSame(
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			$headers['Content-Type'],
		);
	}

	public function testGemaTemplateStreamsStoredFile(): void {
		$response = $this->controller->getGemaTemplate();

		$expectedPath = realpath(
			__DIR__ . '/../../../public/gema-template.xlsx'
		);
		$this->assertNotFalse($expectedPath);

		$streamedContent = null;

		$output = $this->createMock(IOutput::class);

		$output->method('getHttpResponseCode')
			->willReturn(Http::STATUS_OK);

		$output->expects($this->once())
			->method('setReadfile')
			->willReturnCallback(
				function ($path) use (&$streamedContent): bool {
					$streamedContent = file_get_contents($path);
					return $streamedContent !== false;
				}
			);

		$response->callback($output);

		$this->assertSame(
			file_get_contents($expectedPath),
			$streamedContent,
		);
	}

	public function testGemaTemplateCaching(): void {
		$response = $this->controller->getGemaTemplate();
		$headers = $response->getHeaders();

		$this->assertSame(
			'private, max-age=3600, must-revalidate',
			$headers['Cache-Control'],
		);

		$this->assertArrayHasKey('Expires', $headers);
	}

	public function testGemaTemplateNotFound(): void {
		$controller = new class('orchestrascoresmanager', $this->createMock(IRequest::class), ) extends AssetsController {
			protected function getGemaTemplatePath(): string {
				return '/nonexistent/osm-gema-template.xlsx';
			}
		};

		$response = $controller->getGemaTemplate();

		$this->assertInstanceOf(NotFoundResponse::class, $response);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
