<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Mapper integration tests.
 *
 * Provides common setup/teardown for database transactions and foreign key constraints.
 */
abstract class MapperTestCase extends TestCase {
	protected IDBConnection $db;

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->get(IDBConnection::class);

		// CRITICAL: Enable foreign keys BEFORE starting transaction in SQLite
		// PRAGMA settings cannot be changed within a transaction
		if ($this->db->getDatabaseProvider() === IDBConnection::PLATFORM_SQLITE) {
			$this->db->executeStatement('PRAGMA foreign_keys = ON');
		}

		$this->db->beginTransaction();
	}

	protected function tearDown(): void {
		$this->db->rollBack();
		parent::tearDown();
	}
}
