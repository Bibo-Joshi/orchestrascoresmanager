<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * ScoreAudio entity representing an external audio example linked to a score.
 *
 * Exactly one source type is used: either a generic link (provider and providerId are null)
 * or a known provider (provider and providerId are set, link is null).
 * The invariant is validated by ScoreAudioService and, where supported, by a database CHECK constraint.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method int getScoreId()
 * @method void setScoreId(int $value)
 * @method string|null getProvider()
 * @method void setProvider(?string $value)
 * @method string|null getProviderId()
 * @method void setProviderId(?string $value)
 * @method string|null getLink()
 * @method void setLink(?string $value)
 * @method string|null getComment()
 * @method void setComment(?string $value)
 */
class ScoreAudio extends Entity implements JsonSerializable {
	protected int $scoreId = 0;
	protected ?string $provider = null;
	protected ?string $providerId = null;
	protected ?string $link = null;
	protected ?string $comment = null;

	public function __construct() {
		$this->addType('scoreId', Types::BIGINT);
		$this->addType('provider', Types::STRING);
		$this->addType('providerId', Types::STRING);
		$this->addType('link', Types::STRING);
		$this->addType('comment', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'scoreId' => $this->scoreId,
			'provider' => $this->provider,
			'providerId' => $this->providerId,
			'link' => $this->link,
			'comment' => $this->comment,
		];
	}
}
