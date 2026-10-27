<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Enum\AudioProvider;
use OCA\OrchestraScoresManager\Db\ScoreAudio;
use OCA\OrchestraScoresManager\Db\ScoreAudioMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\ScoreAudioPolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

class ScoreAudioService {
	private const MAX_LINK_LENGTH = 2048;
	private const MAX_PROVIDER_ID_LENGTH = 255;
	private const MAX_COMMENT_LENGTH = 1000;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ScoreAudioMapper $scoreAudioMapper,
		private readonly ScoreMapper $scoreMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly ScoreAudioPolicy $scoreAudioPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all audio examples for a given score.
	 *
	 * @param int $scoreId
	 * @return array<ScoreAudio>
	 * @throws Exception
	 */
	public function getAudioExamplesForScore(int $scoreId): array {
		$this->authorizationService->authorizePolicy($this->scoreAudioPolicy, PolicyInterface::ACTION_READ);
		return $this->scoreAudioMapper->findByScoreId($scoreId);
	}

	/**
	 * Create a new audio example for a score if authorized.
	 *
	 * Enforces that an audio example has exactly one type of source: either a generic link (without provider
	 * and provider ID) or a supported provider with provider ID (without link). The database does not
	 * enforce this invariant, so this validation is the only guard.
	 *
	 * @param int $scoreId The ID of the score
	 * @param string|null $provider The provider identifier, e.g. 'youtube'
	 * @param string|null $providerId The provider-specific identifier
	 * @param string|null $link The external URL for generic examples
	 * @param string|null $comment Optional description
	 * @return ScoreAudio
	 * @throws InvalidArgumentException if the score does not exist or the parameters are invalid
	 * @throws Exception
	 */
	public function createAudioExample(
		int $scoreId,
		?string $provider = null,
		?string $providerId = null,
		?string $link = null,
		?string $comment = null,
	): ScoreAudio {
		$this->authorizationService->authorizePolicy($this->scoreAudioPolicy, PolicyInterface::ACTION_CREATE);

		$provider = $this->normalize($provider);
		$providerId = $this->normalize($providerId);
		$link = $this->normalize($link);
		$comment = $this->normalize($comment);

		$this->validateSource($provider, $providerId, $link);
		if ($comment !== null && mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
			throw new InvalidArgumentException($this->l->t('The comment must not be longer than %d characters', [self::MAX_COMMENT_LENGTH]));
		}

		try {
			$this->scoreMapper->find($scoreId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Score not found'));
		}

		$audio = ScoreAudio::fromParams([
			'scoreId' => $scoreId,
			'provider' => $provider,
			'providerId' => $providerId,
			'link' => $link,
			'comment' => $comment,
		]);
		return $this->scoreAudioMapper->insert($audio);
	}

	/**
	 * Delete an audio example if authorized.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the audio example does not exist
	 * @throws Exception
	 */
	public function deleteAudioExample(int $id): void {
		$this->authorizationService->authorizePolicy($this->scoreAudioPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$audio = $this->scoreAudioMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Audio example not found'));
		}
		$this->scoreAudioMapper->delete($audio);
	}

	/**
	 * Trim a value and convert empty strings to null.
	 */
	private function normalize(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}

	/**
	 * Validate that exactly one type of source is given and that it is well-formed.
	 *
	 * @throws InvalidArgumentException
	 */
	private function validateSource(?string $provider, ?string $providerId, ?string $link): void {
		if ($provider === null && $providerId === null) {
			if ($link === null) {
				throw new InvalidArgumentException($this->l->t('Either a link or a provider with provider ID is required'));
			}
			$this->validateLink($link);
			return;
		}

		if ($link !== null) {
			throw new InvalidArgumentException($this->l->t('An audio example must not contain both a link and a provider'));
		}
		if ($provider === null || $providerId === null) {
			throw new InvalidArgumentException($this->l->t('Provider and provider ID must be set together'));
		}
		if (AudioProvider::tryFrom($provider) === null) {
			throw new InvalidArgumentException($this->l->t('Unsupported audio provider "%s"', [$provider]));
		}
		if (strlen($providerId) > self::MAX_PROVIDER_ID_LENGTH || preg_match('/^[A-Za-z0-9_\-.~\/?=&%]+$/', $providerId) !== 1) {
			throw new InvalidArgumentException($this->l->t('Invalid provider ID'));
		}
	}

	/**
	 * Validate that the link is a well-formed http(s) URL.
	 *
	 * @throws InvalidArgumentException
	 */
	private function validateLink(string $link): void {
		$scheme = parse_url($link, PHP_URL_SCHEME);
		if (
			strlen($link) > self::MAX_LINK_LENGTH
			|| filter_var($link, FILTER_VALIDATE_URL) === false
			|| !is_string($scheme)
			|| !in_array(strtolower($scheme), ['http', 'https'], true)
		) {
			throw new InvalidArgumentException($this->l->t('The link must be a valid http(s) URL'));
		}
	}
}
