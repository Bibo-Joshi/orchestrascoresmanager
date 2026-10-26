<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Controller;

use OCA\OrchestraScoresManager\Controller\ScoreAudioApiController;
use OCA\OrchestraScoresManager\Db\ScoreAudio;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\ScoreAudioService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreAudioApiController.
 */
final class ScoreAudioApiControllerTest extends TestCase {
	private ScoreAudioService $scoreAudioService;
	private ScoreAudioApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreAudioService = $this->createMock(ScoreAudioService::class);

		$this->controller = new ScoreAudioApiController(
			'orchestrascoresmanager',
			$this->createMock(IRequest::class),
			$this->scoreAudioService
		);
	}

	public function testGetScoreAudioExamplesReturnsList(): void {
		$audio = new ScoreAudio();
		$audio->setId(1);
		$audio->setScoreId(5);
		$audio->setLink('https://example.org/a');

		$this->scoreAudioService->expects($this->once())
			->method('getAudioExamplesForScore')
			->with(5)
			->willReturn([$audio]);

		$response = $this->controller->getScoreAudioExamples(5);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame([$audio], $response->getData());
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Not authorized'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Bad request'])]
	public function testGetScoreAudioExamplesThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->scoreAudioService->method('getAudioExamplesForScore')
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->getScoreAudioExamples(5);
	}

	public function testPostScoreAudioExampleReturnsCreated(): void {
		$audio = new ScoreAudio();
		$audio->setId(1);
		$audio->setScoreId(5);
		$audio->setProvider('youtube');
		$audio->setProviderId('dQw4w9WgXcQ');
		$audio->setComment('Nice');

		$this->scoreAudioService->expects($this->once())
			->method('createAudioExample')
			->with(5, 'youtube', 'dQw4w9WgXcQ', null, 'Nice')
			->willReturn($audio);

		$response = $this->controller->postScoreAudioExample(5, 'youtube', 'dQw4w9WgXcQ', null, 'Nice');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame($audio, $response->getData());
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testPostScoreAudioExampleWithGenericLink(): void {
		$audio = new ScoreAudio();

		$this->scoreAudioService->expects($this->once())
			->method('createAudioExample')
			->with(5, null, null, 'https://example.org/a', null)
			->willReturn($audio);

		$response = $this->controller->postScoreAudioExample(5, link: 'https://example.org/a');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Not authorized'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Unsupported audio provider'])]
	public function testPostScoreAudioExampleThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->scoreAudioService->method('createAudioExample')
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->postScoreAudioExample(5, 'soundcloud', 'abc');
	}

	public function testDeleteAudioExampleReturnsEmptyResponse(): void {
		$this->scoreAudioService->expects($this->once())
			->method('deleteAudioExample')
			->with(1);

		$response = $this->controller->deleteAudioExample(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame([], $response->getData());
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	#[TestWith([PermissionDeniedException::class, OCSForbiddenException::class, 'Not authorized'])]
	#[TestWith([\InvalidArgumentException::class, OCSBadRequestException::class, 'Audio example not found'])]
	public function testDeleteAudioExampleThrowsExceptions(string $serviceException, string $expectedOCSException, string $message): void {
		$this->scoreAudioService->method('deleteAudioExample')
			->willThrowException(new $serviceException($message));

		$this->expectException($expectedOCSException);
		$this->expectExceptionMessage($message);

		$this->controller->deleteAudioExample(1);
	}
}
