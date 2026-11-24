<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use DateTimeImmutable;
use OCA\OrchestraScoresManager\Db\Setlist;
use OCA\OrchestraScoresManager\Db\SetlistEntry;
use OCA\OrchestraScoresManager\Db\SetlistEntryMapper;
use OCA\OrchestraScoresManager\Db\SetlistMapper;
use OCA\OrchestraScoresManager\Db\SetlistTemplate;
use OCA\OrchestraScoresManager\Db\SetlistTemplateMapper;
use OCA\OrchestraScoresManager\Policy\Scope\SetlistScope;
use OCA\OrchestraScoresManager\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;

final class SetlistTemplateMapperTest extends MapperTestCase {
	private SetlistTemplateMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new SetlistTemplateMapper($this->db);
	}

	private function createTemplate(string $name): SetlistTemplate {
		$now = new DateTimeImmutable();

		return $this->mapper->insert(SetlistTemplate::fromParams([
			'name' => $name,
			'description' => null,
			'configJson' => '{"fontSizes":{"title":16},"margin":8,"cellPadding":1.5,"rules":[]}',
			'createdBy' => 'editor',
			'createdAt' => $now,
			'updatedAt' => $now,
		]));
	}

	public function testInsertAndFind(): void {
		$created = $this->createTemplate('Stage');

		$this->assertGreaterThan(0, $created->getId());

		$found = $this->mapper->find($created->getId());

		$this->assertSame($created->getId(), $found->getId());
		$this->assertSame('Stage', $found->getName());
		$this->assertSame('editor', $found->getCreatedBy());
		$this->assertSame(16, $found->jsonSerialize()['fontSizes']['title']);
	}

	public function testFindAllOrdersByName(): void {
		$this->createTemplate('Stage');
		$this->createTemplate('Concert');

		$templates = $this->mapper->findAll();

		$this->assertCount(2, $templates);
		$this->assertSame('Concert', $templates[0]->getName());
		$this->assertSame('Stage', $templates[1]->getName());
	}

	public function testUpdate(): void {
		$template = $this->createTemplate('Stage');
		$template->setName('Updated');

		$this->mapper->update($template);

		$found = $this->mapper->find($template->getId());
		$this->assertSame('Updated', $found->getName());
	}

	public function testDelete(): void {
		$template = $this->createTemplate('Stage');
		$id = $template->getId();

		$this->mapper->delete($template);

		$this->expectException(DoesNotExistException::class);
		$this->mapper->find($id);
	}

	public function testFindUnknownTemplate(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->find(999999);
	}

	public function testDeleteAndClearDefaults(): void {
		$template = $this->createTemplate('Stage');
		$other = $this->createTemplate('Concert');

		$permissionService = $this->createMock(PermissionService::class);
		$setlistMapper = new SetlistMapper($this->db, new SetlistScope($permissionService));
		$entryMapper = new SetlistEntryMapper($this->db);

		$setlists = [];
		foreach ([$template->getId(), $template->getId(), $other->getId(), null] as $index => $templateId) {
			$setlist = new Setlist();
			$setlist->setTitle('Setlist ' . $index);
			$setlist->setDefaultTemplateId($templateId);
			$setlists[] = $setlistMapper->insert($setlist);
		}

		$entry = new SetlistEntry();
		$entry->setSetlistId($setlists[0]->getId());
		$entry->setIndex(0);
		$entry->setBreakDuration(300);
		$entryMapper->insert($entry);

		$this->mapper->deleteAndClearDefaults($template);

		foreach ([null, null, $other->getId(), null] as $index => $expected) {
			$found = $setlistMapper->find($setlists[$index]->getId());
			$this->assertSame($expected, $found->getDefaultTemplateId());
			$this->assertSame('Setlist ' . $index, $found->getTitle());
		}

		$entries = $entryMapper->findBySetlistId($setlists[0]->getId());
		$this->assertCount(1, $entries);
		$this->assertSame(300, $entries[0]->getBreakDuration());

		$this->assertSame($other->getId(), $this->mapper->find($other->getId())->getId());

		$this->expectException(DoesNotExistException::class);
		$this->mapper->find($template->getId());
	}

}
