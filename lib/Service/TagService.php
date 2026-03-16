<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\TagPolicy;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;

class TagService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly TagMapper $tagMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly TagPolicy $tagPolicy,
	) {
	}

	/**
	 * Return all available tags.
	 * No authorization required.
	 */
	public function getAllTags(): array {
		$this->authorizationService->authorizePolicy($this->tagPolicy, PolicyInterface::ACTION_READ);
		return $this->tagMapper->findAll();
	}

	/**
	 * Create a new tag if authorized.
	 * Default-deny: if authorization fails, an exception is thrown.
	 *
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 * @throws InvalidArgumentException if tag exists or params invalid
	 */
	public function createTag(string $name): Tag {
		// use policy-based authorization
		$this->authorizationService->authorizePolicy($this->tagPolicy, PolicyInterface::ACTION_CREATE);

		$existing = $this->tagMapper->findByName($name);
		if ($existing !== null) {
			return $existing;
		}

		$tag = Tag::fromParams(['name' => $name]);
		return $this->tagMapper->insert($tag);
	}
}
