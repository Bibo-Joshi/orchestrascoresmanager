<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Comment entity representing a comment on a score.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @method int getId()
 * @method void setId(int $value)
 * @method string getContent()
 * @method void setContent(string $value)
 * @method int getCreationDate()
 * @method void setCreationDate(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method int getScoreId()
 * @method void setScoreId(int $value)
 */
class Comment extends Entity implements JsonSerializable {
	protected string $content = '';
	protected int $creationDate = 0;
	protected string $userId = '';
	protected int $scoreId = 0;

	// Transient property - not stored in DB
	private ?string $authorDisplayName = null;

	public function __construct() {
		$this->addType('content', Types::STRING);
		$this->addType('creationDate', Types::BIGINT);
		$this->addType('userId', Types::STRING);
		$this->addType('scoreId', Types::BIGINT);
	}

	/**
	 * Set the display name of the author (transient, not persisted to DB).
	 *
	 * @param string|null $displayName
	 */
	public function setAuthorDisplayName(?string $displayName): void {
		$this->authorDisplayName = $displayName;
	}

	/**
	 * Get the display name of the author (transient).
	 *
	 * @return string|null
	 * @psalm-suppress PossiblyUnusedMethod - Called by CommentService
	 */
	public function getAuthorDisplayName(): ?string {
		return $this->authorDisplayName;
	}

	public function jsonSerialize(): array {
		$author = [
			'userId' => $this->userId,
		];

		// Only include displayName if it's been set
		if ($this->authorDisplayName !== null) {
			$author['displayName'] = $this->authorDisplayName;
		}

		return [
			'id' => $this->id,
			'content' => $this->content,
			'creationDate' => $this->creationDate,
			'author' => $author,
			'scoreId' => $this->scoreId,
		];
	}
}
