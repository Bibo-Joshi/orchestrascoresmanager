<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\OrchestraScoresManager\Migration\Version001003003Date20261118000000;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Schema-level migration checks without modifying an actual Nextcloud DB.
 * The resulting DDL still needs to be tested on a disposable installation.
 */
final class SetlistTemplateMigrationTest extends TestCase {
	public function testSqliteUpgradeAddsInlineReference(): void {
		$setlists = new Table('custom_osm_setlists');
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('getTable')->with('osm_setlists')->willReturn($setlists);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_SQLITE);
		$statements = [
			'ALTER TABLE "custom_osm_setlists" ADD COLUMN default_template_id VARCHAR(64) DEFAULT NULL '
			. 'CONSTRAINT fk_osm_setlists_template REFERENCES "custom_osm_setlist_templates" (id) ON DELETE SET NULL',
			'CREATE INDEX idx_osm_sl_template ON "custom_osm_setlists" (default_template_id)',
		];
		$db->expects($this->exactly(2))->method('executeStatement')->willReturnCallback(
			function (string $sql) use (&$statements): int {
				$this->assertSame(array_shift($statements), $sql);
				return 0;
			}
		);

		(new Version001003003Date20261118000000($db))->preSchemaChange(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			['tablePrefix' => 'custom_']
		);
	}

	public function testSqliteUpgradeRejectsPartialColumnWithoutForeignKey(): void {
		$setlists = new Table('oc_osm_setlists');
		$setlists->addColumn('default_template_id', 'string', ['notnull' => false, 'length' => 64]);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('getTable')->with('osm_setlists')->willReturn($setlists);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_SQLITE);
		$db->expects($this->never())->method('executeStatement');

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('already exists without its foreign key');
		(new Version001003003Date20261118000000($db))->preSchemaChange(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			['tablePrefix' => 'oc_']
		);
	}

	public function testMigrationCreatesTemplateTableAndNullableForeignKey(): void {
		$templateTable = new Table('oc_osm_setlist_templates');
		$setlistTable = new Table('oc_osm_setlists');
		$setlistTable->addColumn('id', 'bigint');

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(
			static fn (string $name): bool => $name === 'osm_setlists'
		);
		$schema->expects($this->once())->method('createTable')
			->with('osm_setlist_templates')->willReturn($templateTable);
		$schema->method('getTable')->willReturnCallback(
			static fn (string $name): Table => match ($name) {
				'osm_setlists' => $setlistTable,
				'osm_setlist_templates' => $templateTable,
			}
		);

		$migration = new Version001003003Date20261118000000($this->createMock(IDBConnection::class));
		$this->assertSame($schema, $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		));

		foreach (['id', 'name', 'description', 'config_json', 'created_by', 'created_at', 'updated_at'] as $column) {
			$this->assertTrue($templateTable->hasColumn($column), $column);
		}
		$this->assertTrue($setlistTable->hasColumn('default_template_id'));
		$this->assertFalse($setlistTable->getColumn('default_template_id')->getNotnull());
		$foreignKeys = array_values($setlistTable->getForeignKeys());
		$this->assertCount(1, $foreignKeys);
		$this->assertSame('oc_osm_setlist_templates', $foreignKeys[0]->getForeignTableName());
		$this->assertSame(['default_template_id'], $foreignKeys[0]->getLocalColumns());
		$this->assertSame(['id'], $foreignKeys[0]->getForeignColumns());
		$this->assertSame('SET NULL', $foreignKeys[0]->getOption('onDelete'));
	}

	public function testMigrationCompletesPartialSchemaAndCanBeRetried(): void {
		$templateTable = new Table('oc_osm_setlist_templates');
		$setlistTable = new Table('oc_osm_setlists');
		$templateTable->addColumn('id', 'string', ['length' => 64]);
		$templateTable->addColumn('name', 'string', ['length' => 160]);
		$templateTable->setPrimaryKey(['id']);
		$setlistTable->addColumn('default_template_id', 'string', ['notnull' => false, 'length' => 64]);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->expects($this->never())->method('createTable');
		$schema->method('getTable')->willReturnMap([
			['osm_setlists', $setlistTable],
			['osm_setlist_templates', $templateTable],
		]);

		$migration = new Version001003003Date20261118000000($this->createMock(IDBConnection::class));
		$this->assertSame($schema, $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		));
		$this->assertTrue($templateTable->hasIndex('idx_osm_st_name'));
		$this->assertTrue($setlistTable->hasIndex('idx_osm_sl_template'));
		$this->assertCount(1, $setlistTable->getForeignKeys());
		$migration->changeSchema($this->createMock(IOutput::class), static fn (): ISchemaWrapper => $schema, []);
		$this->assertCount(1, $setlistTable->getForeignKeys());
	}
}
