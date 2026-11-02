<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\Scope\EntityScopeInterface;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCA\OrchestraScoresManager\Tests\integration\Db\MapperTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base test case checking that a Scope selects exactly the rows its Policy allows.
 *
 * For every action and permission scenario, the IDs returned by the scoped query must equal
 * the IDs of the seeded entities for which Policy::allows() is true.
 */
abstract class ScopePolicyTestCase extends MapperTestCase {
	private bool $canEdit = false;

	/**
	 * @return string Table name (without prefix) the scope applies to
	 */
	abstract protected function tableName(): string;

	/**
	 * @param PermissionService $permissionService
	 * @return PolicyInterface
	 */
	abstract protected function createPolicy(PermissionService $permissionService): PolicyInterface;

	/**
	 * @param PermissionService $permissionService
	 * @return EntityScopeInterface
	 */
	abstract protected function createScope(PermissionService $permissionService): EntityScopeInterface;

	/**
	 * Insert varied rows.
	 *
	 * @return array<int, object> Entity to pass to the policy, indexed by row ID
	 */
	abstract protected function seed(): array;

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function actionScenarioProvider(): array {
		$actions = [
			PolicyInterface::ACTION_CREATE,
			PolicyInterface::ACTION_READ,
			PolicyInterface::ACTION_UPDATE,
			PolicyInterface::ACTION_DELETE,
			PolicyInterface::ACTION_SHARE,
			'UNKNOWN',
		];
		$cases = [];
		foreach ($actions as $action) {
			$cases["$action as editor"] = [$action, true];
			$cases["$action as non-editor"] = [$action, false];
			$cases["$action as anonymous"] = [$action, false];
		}
		return $cases;
	}

	#[DataProvider('actionScenarioProvider')]
	public function testScopeMatchesPolicy(string $action, bool $canEdit): void {
		$this->canEdit = $canEdit;
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canCurrentUserEdit')->willReturnCallback(fn (): bool => $this->canEdit);
		$policy = $this->createPolicy($permissionService);
		$scope = $this->createScope($permissionService);

		$seeded = $this->seed();
		$this->assertNotEmpty($seeded);

		$expected = [];
		foreach ($seeded as $id => $entity) {
			if ($policy->allows($action, $entity)) {
				$expected[] = $id;
			}
		}
		sort($expected);

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->tableName());
		$scope->apply($action, $qb);
		$qb->andWhere($qb->expr()->in('id', $qb->createNamedParameter(array_keys($seeded), IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$actual = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();
		sort($actual);

		$this->assertSame($expected, $actual);
	}

	public function testUnknownActionIsDenied(): void {
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canCurrentUserEdit')->willReturn(true);
		$seeded = $this->seed();

		$this->assertFalse($this->createPolicy($permissionService)->allows('UNKNOWN', current($seeded)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->tableName());
		$this->createScope($permissionService)->apply('UNKNOWN', $qb);
		$qb->andWhere($qb->expr()->in('id', $qb->createNamedParameter(array_keys($seeded), IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$this->assertSame([], $result->fetchFirstColumn());
		$result->closeCursor();
	}
}
