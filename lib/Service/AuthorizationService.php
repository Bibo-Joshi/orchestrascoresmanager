<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AuthorizationService {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly string $appName,
		private IL10N $l,
	) {
	}

	/**
	 * Authorize based on a Policy implementation.
	 * Throws PermissionDeniedException on deny.
	 */
	public function authorizePolicy(PolicyInterface $policy, string $action, ?object $subject = null): void {
		if (!$policy->allows($action, $subject)) {
			$this->logger->warning('Policy denied', [
				'app' => $this->appName,
				'policy' => $policy::class,
				'action' => $action,
				'subject' => $subject ? get_class($subject) : null,
			]);
			throw new PermissionDeniedException($this->l->t('Insufficient permissions'));
		}
	}
}
