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
}
