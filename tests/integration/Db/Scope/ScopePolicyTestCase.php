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

	/**
	 * Compares the two implementations of the same rule: Policy::allows() decides per entity in PHP,
	 * Scope::apply() filters rows in SQL. Both must select the same rows.
	 *
	 * @param string $action Action to check
	 * @param bool $canEdit Result of PermissionService::canCurrentUserEdit(), false also covers anonymous users
	 */
	#[DataProvider('actionScenarioProvider')]
	public function testScopeMatchesPolicy(string $action, bool $canEdit): void {
		$this->canEdit = $canEdit;
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canCurrentUserEdit')->willReturnCallback(fn (): bool => $this->canEdit);
		$policy = $this->createPolicy($permissionService);
		$scope = $this->createScope($permissionService);

		// Insert the test rows. This runs inside the transaction started by MapperTestCase and is rolled back afterwards.
		$seeded = $this->seed();
		$this->assertNotEmpty($seeded);

		// Expected result: IDs of the rows for which the policy allows the action
		$expected = [];
		foreach ($seeded as $id => $entity) {
			if ($policy->allows($action, $entity)) {
				$expected[] = $id;
			}
		}
		sort($expected);

		// Actual result: SELECT id FROM <table> WHERE <scope condition> AND id IN (<seeded IDs>)
		// The scope adds its condition to the query. The IN clause restricts the query to the seeded rows,
		// so rows that already exist in the database do not influence the comparison.
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

	/**
	 * Unknown actions must be denied by default: the policy returns false and the scope matches no rows,
	 * even for users with edit rights.
	 */
	public function testUnknownActionIsDenied(): void {
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canCurrentUserEdit')->willReturn(true);
		$seeded = $this->seed();

		$this->assertFalse($this->createPolicy($permissionService)->allows('UNKNOWN', current($seeded)));
		// SELECT id FROM <table> WHERE 1 = 0 AND id IN (<seeded IDs>) must return nothing
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->tableName());
		$this->createScope($permissionService)->apply('UNKNOWN', $qb);
		$qb->andWhere($qb->expr()->in('id', $qb->createNamedParameter(array_keys($seeded), IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$this->assertSame([], $result->fetchFirstColumn());
		$result->closeCursor();
	}
}
