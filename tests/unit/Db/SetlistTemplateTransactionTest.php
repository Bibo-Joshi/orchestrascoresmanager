<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateTransactionTest extends TestCase {
	public function testDeleteRollsBackOnFailure(): void {
		$db = $this->createMock(IDBConnection::class);
		$exception = new \RuntimeException('Database operation failed');

		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('rollBack');
		$db->expects($this->never())->method('commit');

		$db->method('getQueryBuilder')->willThrowException($exception);

		$mapper = new SetlistTemplateMapper($db);
		$template = new SetlistTemplate();
		$template->setId(1);

		$this->expectExceptionObject($exception);
		$mapper->deleteAndClearDefaults($template);
	}
}
