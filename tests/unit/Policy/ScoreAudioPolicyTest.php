<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Policy;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\ScoreAudioPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreAudioPolicy.
 *
 * Audio examples are a community effort, so all actions are allowed independent of edit rights.
 */
final class ScoreAudioPolicyTest extends TestCase {
	private ScoreAudioPolicy $policy;

	protected function setUp(): void {
		parent::setUp();

		$this->policy = new ScoreAudioPolicy();
	}

	#[DataProvider('allowsProvider')]
	public function testAllows(string $action, bool $expectedResult): void {
		$this->assertSame($expectedResult, $this->policy->allows($action));
	}

	public static function allowsProvider(): array {
		return [
			'CREATE action is allowed' => [PolicyInterface::ACTION_CREATE, true],
			'READ action is allowed' => [PolicyInterface::ACTION_READ, true],
			'UPDATE action is allowed' => [PolicyInterface::ACTION_UPDATE, true],
			'DELETE action is allowed' => [PolicyInterface::ACTION_DELETE, true],
			'SHARE action is allowed' => [PolicyInterface::ACTION_SHARE, true],
			'Unknown action is denied' => ['UNKNOWN_ACTION', false],
		];
	}
}
