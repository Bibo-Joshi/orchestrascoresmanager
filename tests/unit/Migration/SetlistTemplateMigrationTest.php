<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Migration;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BigIntType;
use OCA\OrchestraScoresManager\Migration\Version001003003Date20261118000000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateMigrationTest extends TestCase {
	private function schemaTable(Table $table): object {
		if (interface_exists(\OCP\DB\Schema\ITable::class)) {
			return new \OC\DB\Schema\Table($table);
		}
		return $table;
	}

	public function testMigrationCreatesTemplateTableAndNullableTemplateId(): void {
		$templateTable = new Table('oc_osm_setlist_templates');
		$setlistTable = new Table('oc_osm_setlists');
		$setlistTable->addColumn('id', 'bigint');

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')
			->with('osm_setlist_templates')
			->willReturn(false);

		$schema->expects($this->once())
			->method('createTable')
			->with('osm_setlist_templates')
			->willReturn($this->schemaTable($templateTable));

		$schema->method('getTable')->willReturnCallback(
			fn (string $name): object => match ($name) {
				'osm_setlists' => $this->schemaTable($setlistTable),
				'osm_setlist_templates' => $this->schemaTable($templateTable),
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

		$id = $templateTable->getColumn('id');
		$this->assertInstanceOf(BigIntType::class, $id->getType());
		$this->assertTrue($id->getAutoincrement());
		$this->assertTrue($id->getUnsigned());
		$this->assertTrue($id->getNotnull());

		$defaultTemplate = $setlistTable->getColumn('default_template_id');
		$this->assertInstanceOf(BigIntType::class, $defaultTemplate->getType());
		$this->assertTrue($defaultTemplate->getUnsigned());
		$this->assertFalse($defaultTemplate->getNotnull());

		$this->assertCount(0, $setlistTable->getForeignKeys());
	}

	public function testMigrationCanBeRepeated(): void {
		$templateTable = new Table('oc_osm_setlist_templates');
		$templateTable->addColumn('id', 'bigint');
		$templateTable->setPrimaryKey(['id']);

		$setlistTable = new Table('oc_osm_setlists');
		$setlistTable->addColumn('default_template_id', 'bigint', [
			'notnull' => false,
			'unsigned' => true,
		]);

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->expects($this->never())->method('createTable');
		$schema->method('getTable')
			->with('osm_setlists')
			->willReturn($this->schemaTable($setlistTable));

		$migration = new Version001003003Date20261118000000();

		$this->assertSame($schema, $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		));

		$this->assertCount(0, $setlistTable->getForeignKeys());
	}
}
