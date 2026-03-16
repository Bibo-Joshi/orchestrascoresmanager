<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\OrchestraScoresManager\AppInfo\Application::APP_ID, OCA\OrchestraScoresManager\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\OrchestraScoresManager\AppInfo\Application::APP_ID, OCA\OrchestraScoresManager\AppInfo\Application::APP_ID . '-main');

?>

<div id="orchestrascoresmanager"></div>
