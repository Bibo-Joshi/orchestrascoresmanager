<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCP\IUserSession;

/**
 * Shared template operations and authorization, independent of the PDF renderer.
 */
class SetlistTemplateService {
	/** @psalm-suppress PossiblyUnusedMethod - Service instantiated by Nextcloud DI */
	public function __construct(
		private readonly SetlistTemplateMapper $mapper,
		private readonly SetlistTemplateValidator $validator,
		private readonly AuthorizationService $authorizationService,
		private readonly SetlistTemplatePolicy $policy,
		private readonly IUserSession $userSession,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function getTemplates(): array {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_READ);
		return $this->mapper->findAll();
	}

	/** @return array<string, mixed> */
	public function createTemplate(array $input): array {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_CREATE);
		$validated = $this->validator->validate($input);
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			throw new \InvalidArgumentException('User not authenticated');
		}
		$id = bin2hex(random_bytes(16));
		return $this->mapper->create(
			$id, $validated['name'], $validated['description'], $validated['config'], $userId
		);
	}

	/** @return array<string, mixed> */
	public function patchTemplate(string $id, array $changes): array {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_UPDATE);
		$previous = $this->findExisting($id);
		if (isset($changes['id']) && $changes['id'] !== $id) {
			throw new \InvalidArgumentException('Template ID cannot be modified');
		}
		unset($previous['createdBy'], $previous['createdAt'], $previous['updatedAt']);
		// PATCH can update one font size without discarding the other values.
		if (isset($changes['fontSizes']) && is_array($changes['fontSizes'])) {
			if (!isset($previous['fontSizes']) || !is_array($previous['fontSizes'])) {
				throw new \UnexpectedValueException('Stored template fontSizes are invalid');
			}
			$changes['fontSizes'] = array_replace($previous['fontSizes'], $changes['fontSizes']);
		}
		$validated = $this->validator->validate(array_replace($previous, $changes));
		return $this->mapper->update(
			$id, $validated['name'], $validated['description'], $validated['config']
		);
	}

	public function deleteTemplate(string $id): void {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_DELETE);
		$this->findExisting($id);
		$this->mapper->delete($id);
	}

	/** @return array<string, mixed> */
	private function findExisting(string $id): array {
		return $this->mapper->find($id) ?? throw new \InvalidArgumentException('Template not found');
	}
}
