<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Policy;

interface PolicyInterface {
	public const ACTION_CREATE = 'CREATE';
	public const ACTION_READ = 'READ';
	public const ACTION_UPDATE = 'UPDATE';
	public const ACTION_DELETE = 'DELETE';
	public const ACTION_SHARE = 'SHARE';

	/**
	 * Decide whether the given action is allowed on the subject.
	 *
	 * @param string $action
	 * @param object|null $subject
	 * @return bool
	 */
	public function allows(string $action, ?object $subject = null): bool;
}
