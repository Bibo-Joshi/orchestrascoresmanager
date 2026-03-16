<?php

declare(strict_types=1);

$ncBootstrap = __DIR__ . '/../../../tests/bootstrap.php';
if (file_exists($ncBootstrap)) {
	require_once $ncBootstrap;
} else {
	// Only define if not in NextCloud context
	if (!defined('PHPUNIT_RUN')) {
		define('PHPUNIT_RUN', 1);
	}
	require_once __DIR__ . '/../vendor/autoload.php';
}

if (class_exists('OC_App')) {
	\OC_App::loadApp(OCA\OrchestraScoresManager\AppInfo\Application::APP_ID);
	OC_Hook::clear();
}
