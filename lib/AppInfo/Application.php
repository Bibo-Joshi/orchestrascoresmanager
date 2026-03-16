<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/** @psalm-suppress UnusedClass - Entry point for NextCloud app framework */
class Application extends App implements IBootstrap {
	public const APP_ID = 'orchestrascoresmanager';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}
