<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\SetlistTemplatePolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

class SetlistTemplateService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly SetlistTemplateMapper $mapper,
		private readonly SetlistTemplateValidator $validator,
		private readonly AuthorizationService $authorizationService,
		private readonly SetlistTemplatePolicy $policy,
		private readonly IUserSession $userSession,
	) {
	}

	/** @return SetlistTemplate[] */
	public function getTemplates(): array {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_READ);
		return $this->mapper->findAll();
	}

	public function createTemplate(array $input): SetlistTemplate {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_CREATE);

		$validated = $this->validator->validate($input);
		$userId = $this->userSession->getUser()?->getUID();

		if ($userId === null) {
			throw new InvalidArgumentException('User not authenticated');
		}

		$now = new DateTimeImmutable();

		$template = SetlistTemplate::fromParams([
			'name' => $validated['name'],
			'description' => $validated['description'],
			'configJson' => json_encode($validated['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
			'createdBy' => $userId,
			'createdAt' => $now,
			'updatedAt' => $now,
		]);

		return $this->mapper->insert($template);
	}

	public function patchTemplate(int $id, array $changes): SetlistTemplate {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_UPDATE);

		$template = $this->findExisting($id);
		$previous = $template->jsonSerialize();

		if (isset($changes['id']) && $changes['id'] !== $id) {
			throw new InvalidArgumentException('Template ID cannot be modified');
		}

		unset($previous['id'], $previous['createdBy'], $previous['createdAt'], $previous['updatedAt']);

		if (isset($changes['fontSizes']) && is_array($changes['fontSizes'])) {
			$previousFontSizes = $previous['fontSizes'] ?? [];
			if (!is_array($previousFontSizes)) {
				throw new \UnexpectedValueException('Stored template fontSizes are invalid');
			}
			$changes['fontSizes'] = array_replace(
				$previousFontSizes,
				$changes['fontSizes']
			);
		}

		$validated = $this->validator->validate(array_replace($previous, $changes));

		$template->setName($validated['name']);
		$template->setDescription($validated['description']);
		$template->setConfigJson(json_encode($validated['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
		$template->setUpdatedAt(new DateTimeImmutable());

		return $this->mapper->update($template);
	}

	public function deleteTemplate(int $id): void {
		$this->authorizationService->authorizePolicy($this->policy, PolicyInterface::ACTION_DELETE);
		$this->mapper->deleteAndClearDefaults($this->findExisting($id));
	}

	private function findExisting(int $id): SetlistTemplate {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new InvalidArgumentException('Template not found', 0, $e);
		}
	}
}
