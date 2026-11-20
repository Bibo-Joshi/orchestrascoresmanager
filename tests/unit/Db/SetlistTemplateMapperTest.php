<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateMapperTest extends TestCase {
	public function testFindAllReadsAndDecodesSharedTemplates(): void {
		$db = $this->createMock(IDBConnection::class);
		$qb = $this->createMock(IQueryBuilder::class);
		$result = $this->createMock(IResult::class);
		$db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);
		$qb->expects($this->once())->method('select')->with('*')->willReturnSelf();
		$qb->expects($this->once())->method('from')->with('osm_setlist_templates')->willReturnSelf();
		$qb->expects($this->once())->method('orderBy')->with('name', 'ASC')->willReturnSelf();
		$qb->expects($this->once())->method('addOrderBy')->with('id', 'ASC')->willReturnSelf();
		$qb->method('executeQuery')->willReturn($result);
		$result->method('fetchAll')->willReturn([[
			'id' => 'stage',
			'name' => 'Stage layout',
			'description' => null,
			'config_json' => '{"fontSizes":{"title":16},"margin":8,"cellPadding":1.5,"rules":[]}',
			'created_by' => 'editor',
			'created_at' => '2026-11-18 12:00:00',
			'updated_at' => '2026-11-18 12:00:00',
		]]);

		$templates = (new SetlistTemplateMapper($db))->findAll();
		$this->assertCount(1, $templates);
		$this->assertSame('stage', $templates[0]['id']);
		$this->assertSame(16, $templates[0]['fontSizes']['title']);
		$this->assertSame('editor', $templates[0]['createdBy']);
	}
	public function testRejectsMalformedStoredConfiguration(): void {
		$db = $this->createMock(IDBConnection::class);
		$qb = $this->createMock(IQueryBuilder::class);
		$result = $this->createMock(IResult::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('orderBy')->willReturnSelf();
		$qb->method('addOrderBy')->willReturnSelf();
		$qb->method('executeQuery')->willReturn($result);
		$result->method('fetchAll')->willReturn([[
			'id' => 'bad',
			'name' => 'Corrupt template',
			'description' => null,
			'config_json' => 'null',
			'created_by' => 'editor',
			'created_at' => '2026-11-18 12:00:00',
			'updated_at' => '2026-11-18 12:00:00',
		]]);

		$this->expectException(\UnexpectedValueException::class);
		(new SetlistTemplateMapper($db))->findAll();
	}

	/** @return array<string, mixed> */
	private static function savedTemplate(string $id = 'stage'): array {
		return [
			'id' => $id,
			'name' => 'Stage layout',
			'description' => 'For concert use',
			'config_json' => '{"fontSizes":{"title":16},"margin":8,"cellPadding":1.5,"rules":[]}',
			'created_by' => 'editor',
			'created_at' => '2026-11-18 12:00:00',
			'updated_at' => '2026-11-18 12:00:00',
		];
	}

	private function readBuilder(array|false $row): IQueryBuilder {
		$qb = $this->createMock(IQueryBuilder::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$result = $this->createMock(IResult::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): mixed => $value);
		$expr->method('eq')->willReturn('id = :id');
		$qb->method('executeQuery')->willReturn($result);
		$result->method('fetchAssociative')->willReturn($row);
		return $qb;
	}

	public function testFindReturnsSerializedTemplate(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('getQueryBuilder')->willReturn($this->readBuilder(self::savedTemplate()));
		$template = (new SetlistTemplateMapper($db))->find('stage');
		$this->assertNotNull($template);
		$this->assertSame('stage', $template['id']);
		$this->assertSame('For concert use', $template['description']);
		$this->assertSame(8, $template['margin']);
	}

	public function testFindReturnsNullForUnknownId(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($this->readBuilder(false));
		$this->assertNull((new SetlistTemplateMapper($db))->find('unknown'));
	}

	public function testCreatePersistsAndReadsBackTemplate(): void {
		$db = $this->createMock(IDBConnection::class);
		$write = $this->createMock(IQueryBuilder::class);
		$write->expects($this->once())->method('insert')->with('osm_setlist_templates')->willReturnSelf();
		$write->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): mixed => $value);
		$values = null;
		$write->expects($this->once())->method('values')->willReturnCallback(
			function (array $given) use (&$values, $write): IQueryBuilder {
				$values = $given;
				return $write;
			}
		);
		$write->expects($this->once())->method('executeStatement')->willReturn(1);
		$db->expects($this->exactly(2))->method('getQueryBuilder')->willReturnOnConsecutiveCalls(
			$write,
			$this->readBuilder(self::savedTemplate('new-id')),
		);
		$created = (new SetlistTemplateMapper($db))->create(
			'new-id', 'Stage layout', 'For concert use',
			['fontSizes' => ['title' => 16], 'margin' => 8, 'cellPadding' => 1.5, 'rules' => []],
			'editor'
		);
		$this->assertSame('new-id', $created['id']);
		$this->assertSame('new-id', $values['id']);
		$this->assertSame('editor', $values['created_by']);
		$this->assertSame(['fontSizes' => ['title' => 16], 'margin' => 8, 'cellPadding' => 1.5, 'rules' => []],
			json_decode($values['config_json'], true, 512, JSON_THROW_ON_ERROR));
		$this->assertSame($values['created_at'], $values['updated_at']);
	}

	public function testCreateReportsMissingReadBack(): void {
		$db = $this->createMock(IDBConnection::class);
		$write = $this->createMock(IQueryBuilder::class);
		$write->method('insert')->willReturnSelf();
		$write->method('values')->willReturnSelf();
		$write->method('createNamedParameter')->willReturn(':param');
		$write->method('executeStatement')->willReturn(1);
		$db->method('getQueryBuilder')->willReturnOnConsecutiveCalls($write, $this->readBuilder(false));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('New template was not found');
		(new SetlistTemplateMapper($db))->create('new-id', 'New', null, ['rules' => []], 'editor');
	}

	public function testUpdatePersistsAndReadsBackTemplate(): void {
		$db = $this->createMock(IDBConnection::class);
		$write = $this->createMock(IQueryBuilder::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('id = :id');
		$write->method('expr')->willReturn($expr);
		$write->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): mixed => $value);
		$write->expects($this->once())->method('update')->with('osm_setlist_templates')->willReturnSelf();
		$changes = [];
		$write->expects($this->exactly(4))->method('set')->willReturnCallback(
			function (string $key, mixed $value) use (&$changes, $write): IQueryBuilder {
				$changes[$key] = $value;
				return $write;
			}
		);
		$write->method('where')->willReturnSelf();
		$write->expects($this->once())->method('executeStatement')->willReturn(1);
		$db->expects($this->exactly(2))->method('getQueryBuilder')->willReturnOnConsecutiveCalls(
			$write,
			$this->readBuilder(self::savedTemplate()),
		);
		$updated = (new SetlistTemplateMapper($db))->update(
			'stage', 'Updated name', null, ['fontSizes' => [], 'margin' => 10, 'rules' => []]
		);
		$this->assertSame('stage', $updated['id']);
		$this->assertSame('Updated name', $changes['name']);
		$this->assertNull($changes['description']);
		$this->assertSame(10, json_decode($changes['config_json'], true)['margin']);
		$this->assertNotEmpty($changes['updated_at']);
	}

	public function testUpdateReportsMissingReadBack(): void {
		$db = $this->createMock(IDBConnection::class);
		$write = $this->createMock(IQueryBuilder::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('id = :id');
		$write->method('expr')->willReturn($expr);
		$write->method('createNamedParameter')->willReturn(':param');
		$write->method('update')->willReturnSelf();
		$write->method('set')->willReturnSelf();
		$write->method('where')->willReturnSelf();
		$write->method('executeStatement')->willReturn(1);
		$db->method('getQueryBuilder')->willReturnOnConsecutiveCalls($write, $this->readBuilder(false));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Updated template was not found');
		(new SetlistTemplateMapper($db))->update('missing', 'New', null, ['rules' => []]);
	}

	public function testDeleteExecutesQueryForId(): void {
		$db = $this->createMock(IDBConnection::class);
		$qb = $this->createMock(IQueryBuilder::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('id = :id');
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('stage');
		$qb->expects($this->once())->method('delete')->with('osm_setlist_templates')->willReturnSelf();
		$qb->expects($this->once())->method('where')->with('id = :id')->willReturnSelf();
		$qb->expects($this->once())->method('executeStatement')->willReturn(1);
		$db->method('getQueryBuilder')->willReturn($qb);
		(new SetlistTemplateMapper($db))->delete('stage');
	}
}
