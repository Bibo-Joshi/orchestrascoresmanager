<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Settings;

use OCA\OrchestraScoresManager\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/** @psalm-suppress UnusedClass - Settings class discovered by NextCloud framework */
class AdminSection implements IIconSection {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * @return string relative path to an 16*16 icon describing the section
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	/**
	 * @return string the ID of the section. It is supposed to be a lower case string
	 */
	public function getID(): string {
		return Application::APP_ID;
	}

	/**
	 * @return string the translated name as it should be displayed
	 */
	public function getName(): string {
		return $this->l10n->t('Orchestra Scores Manager');
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of the settings navigation
	 */
	public function getPriority(): int {
		return 50;
	}
}
