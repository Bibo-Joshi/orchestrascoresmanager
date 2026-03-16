<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\OrchestraScoresManager\AppInfo\Application::APP_ID, OCA\OrchestraScoresManager\AppInfo\Application::APP_ID . '-adminSettings');
Util::addStyle(OCA\OrchestraScoresManager\AppInfo\Application::APP_ID, OCA\OrchestraScoresManager\AppInfo\Application::APP_ID . '-adminSettings');
?>

<div id="orchestra-scores-admin-settings"></div>
