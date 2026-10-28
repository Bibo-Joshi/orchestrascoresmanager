<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreAudio;
use OCA\OrchestraScoresManager\Db\ScoreAudioMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\ScoreAudioPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\ScoreAudioService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreAudioService.
 */
final class ScoreAudioServiceTest extends TestCase {
	private ScoreAudioMapper $scoreAudioMapper;
	private ScoreMapper $scoreMapper;
	private AuthorizationService $authorizationService;
	private ScoreAudioPolicy $scoreAudioPolicy;
	private ScoreAudioService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreAudioMapper = $this->createMock(ScoreAudioMapper::class);
		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->scoreAudioPolicy = $this->createMock(ScoreAudioPolicy::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new ScoreAudioService(
			$this->scoreAudioMapper,
			$this->scoreMapper,
			$this->authorizationService,
			$this->scoreAudioPolicy,
			$l10n,
		);
	}

	public function testGetAudioExamplesForScoreRequiresReadAuthorization(): void {
		$audio = new ScoreAudio();
		$audio->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreAudioPolicy, PolicyInterface::ACTION_READ);
		$this->scoreAudioMapper->expects($this->once())
			->method('findByScoreId')
			->with(5)
			->willReturn([$audio]);

		$this->assertSame([$audio], $this->service->getAudioExamplesForScore(5));
	}

	public function testGetAudioExamplesForScoreDeniedDoesNotQuery(): void {
		$this->authorizationService->method('authorizePolicy')
			->willThrowException(new PermissionDeniedException('Denied'));
		$this->scoreAudioMapper->expects($this->never())->method('findByScoreId');

		$this->expectException(PermissionDeniedException::class);

		$this->service->getAudioExamplesForScore(5);
	}

	public function testCreateGenericAudioExample(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreAudioPolicy, PolicyInterface::ACTION_CREATE);
		$this->scoreMapper->expects($this->once())
			->method('find')
			->with(5)
			->willReturn(new Score());
		$this->scoreAudioMapper->expects($this->once())
			->method('insert')
			->with($this->callback(static fn (ScoreAudio $audio): bool => $audio->getScoreId() === 5
				&& $audio->getProvider() === null
				&& $audio->getProviderId() === null
				&& $audio->getLink() === 'https://example.org/recording.mp3'
				&& $audio->getComment() === 'Live'))
			->willReturnArgument(0);

		$result = $this->service->createAudioExample(5, null, null, '  https://example.org/recording.mp3 ', ' Live ');

		$this->assertSame('https://example.org/recording.mp3', $result->getLink());
	}

	public function testCreateProviderAudioExample(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->scoreAudioMapper->expects($this->once())
			->method('insert')
			->with($this->callback(static fn (ScoreAudio $audio): bool => $audio->getProvider() === 'spotify'
				&& $audio->getProviderId() === 'track/4uLU6hMCjMI75M1A2tKUQC'
				&& $audio->getLink() === null
				&& $audio->getComment() === null))
			->willReturnArgument(0);

		$result = $this->service->createAudioExample(5, 'spotify', 'track/4uLU6hMCjMI75M1A2tKUQC', '', '');

		$this->assertSame('spotify', $result->getProvider());
	}

	#[DataProvider('supportedProviderProvider')]
	public function testCreateAcceptsAllSupportedProviders(string $provider): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->scoreAudioMapper->expects($this->once())->method('insert')->willReturnArgument(0);

		$result = $this->service->createAudioExample(5, $provider, 'some/id-123');

		$this->assertSame($provider, $result->getProvider());
	}

	public static function supportedProviderProvider(): array {
		return [
			['youtube'],
			['spotify'],
			['apple-music'],
			['deezer'],
			['amazon-music'],
		];
	}

	public function testCreateMultipleExamplesOfSameProviderIsAllowed(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->scoreAudioMapper->expects($this->exactly(2))->method('insert')->willReturnArgument(0);

		$this->service->createAudioExample(5, 'youtube', 'aaaaaaaaaaa');
		$this->service->createAudioExample(5, 'youtube', 'bbbbbbbbbbb');
	}

	/**
	 * @param array{0: string|null, 1: string|null, 2: string|null, 3?: string|null} $args provider, providerId, link, comment
	 */
	#[DataProvider('invalidParametersProvider')]
	public function testCreateRejectsInvalidParameters(array $args, string $message): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->scoreAudioMapper->expects($this->never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		$this->service->createAudioExample(5, ...$args);
	}

	public static function invalidParametersProvider(): array {
		return [
			'nothing given' => [[null, null, null], 'Either a link or a provider with provider ID is required'],
			'blank values only' => [['', ' ', ' '], 'Either a link or a provider with provider ID is required'],
			'generic with provider' => [['youtube', null, 'https://example.org/a'], 'must not contain both a link and a provider'],
			'generic with provider id' => [[null, 'abc', 'https://example.org/a'], 'must not contain both a link and a provider'],
			'provider with link' => [['youtube', 'abc', 'https://example.org/a'], 'must not contain both a link and a provider'],
			'provider without id' => [['youtube', null, null], 'Provider and provider ID must be set together'],
			'provider id without provider' => [[null, 'abc', null], 'Provider and provider ID must be set together'],
			'unsupported provider' => [['soundcloud', 'abc', null], 'Unsupported audio provider "soundcloud"'],
			'provider id with illegal characters' => [['youtube', 'abc def', null], 'Invalid provider ID'],
			'provider id with script' => [['youtube', '"><script>', null], 'Invalid provider ID'],
			'provider id too long' => [['youtube', str_repeat('a', 256), null], 'Invalid provider ID'],
			'link is not a url' => [[null, null, 'not a url'], 'The link must be a valid http(s) URL'],
			'link with javascript scheme' => [[null, null, 'javascript:alert(1)'], 'The link must be a valid http(s) URL'],
			'link with ftp scheme' => [[null, null, 'ftp://example.org/a.mp3'], 'The link must be a valid http(s) URL'],
			'link too long' => [[null, null, 'https://example.org/' . str_repeat('a', 2048)], 'The link must be a valid http(s) URL'],
			'comment too long' => [[null, null, 'https://example.org/a', str_repeat('a', 1001)], 'The comment must not be longer than 1000 characters'],
		];
	}

	public function testCreateThrowsWhenScoreDoesNotExist(): void {
		$this->scoreMapper->method('find')->willThrowException(new DoesNotExistException('missing'));
		$this->scoreAudioMapper->expects($this->never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Score not found');

		$this->service->createAudioExample(999, null, null, 'https://example.org/a');
	}

	public function testCreateDeniedDoesNotInsert(): void {
		$this->authorizationService->method('authorizePolicy')
			->willThrowException(new PermissionDeniedException('Denied'));
		$this->scoreAudioMapper->expects($this->never())->method('insert');

		$this->expectException(PermissionDeniedException::class);

		$this->service->createAudioExample(5, null, null, 'https://example.org/a');
	}

	public function testDeleteAudioExample(): void {
		$audio = new ScoreAudio();
		$audio->setId(3);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreAudioPolicy, PolicyInterface::ACTION_DELETE);
		$this->scoreAudioMapper->expects($this->once())
			->method('find')
			->with(3)
			->willReturn($audio);
		$this->scoreAudioMapper->expects($this->once())
			->method('delete')
			->with($audio);

		$this->service->deleteAudioExample(3);
	}

	public function testDeleteThrowsWhenNotFound(): void {
		$this->scoreAudioMapper->method('find')->willThrowException(new DoesNotExistException('missing'));
		$this->scoreAudioMapper->expects($this->never())->method('delete');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Audio example not found');

		$this->service->deleteAudioExample(999);
	}

	public function testDeleteDeniedDoesNotDelete(): void {
		$this->authorizationService->method('authorizePolicy')
			->willThrowException(new PermissionDeniedException('Denied'));
		$this->scoreAudioMapper->expects($this->never())->method('delete');

		$this->expectException(PermissionDeniedException::class);

		$this->service->deleteAudioExample(3);
	}
}
