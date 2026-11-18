<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\OrchestraScoresManager\Migration\Version001003003Date20261118000000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Schema-level migration checks without modifying an actual Nextcloud DB.
 * The resulting DDL still needs to be tested on a disposable installation.
 */
final class SetlistTemplateMigrationTest extends TestCase {
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

		$migration = new Version001003003Date20261118000000();
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

	public function testMigrationDoesNotRecreateExistingSchema(): void {
		$templateTable = new Table('oc_osm_setlist_templates');
		$setlistTable = new Table('oc_osm_setlists');
		$setlistTable->addColumn('default_template_id', 'string', ['notnull' => false]);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->expects($this->never())->method('createTable');
		$schema->method('getTable')->with('osm_setlists')->willReturn($setlistTable);

		$migration = new Version001003003Date20261118000000();
		$this->assertSame($schema, $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		));
		$this->assertSame([], $setlistTable->getForeignKeys());
	}
}
