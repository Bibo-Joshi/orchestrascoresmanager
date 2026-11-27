<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Policy\ConductorsScorePolicy;
use OCA\OrchestraScoresManager\Policy\PartPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\ConductorsScoreService;
use OCA\OrchestraScoresManager\Service\PartService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for PartService and ConductorsScoreService.
 */
final class PartAndConductorsScoreServiceTest extends TestCase {
	private ScoreMapper&MockObject $scoreMapper;
	private IDBConnection&MockObject $db;
	private PartMapper&MockObject $partMapper;
	private PartVariantMapper&MockObject $partVariantMapper;
	private ConductorsScoreMapper&MockObject $csMapper;
	private ConductorsScoreVariantMapper&MockObject $csVariantMapper;
	private PartService $partService;
	private ConductorsScoreService $csService;

	protected function setUp(): void {
		parent::setUp();
		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->partMapper = $this->createMock(PartMapper::class);
		$this->partVariantMapper = $this->createMock(PartVariantMapper::class);
		$this->csMapper = $this->createMock(ConductorsScoreMapper::class);
		$this->csVariantMapper = $this->createMock(ConductorsScoreVariantMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));
		$auth = $this->createMock(AuthorizationService::class);

		$this->partService = new PartService(
			$this->partMapper, $this->partVariantMapper, $this->scoreMapper, $this->db, $auth,
			$this->createMock(PartPolicy::class), $l10n,
		);
		$this->csService = new ConductorsScoreService(
			$this->csMapper, $this->csVariantMapper, $this->scoreMapper, $this->db, $auth,
			$this->createMock(ConductorsScorePolicy::class), $l10n,
		);
	}

	public function testCreatePartNormalizesInput(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->partMapper->method('insert')->willReturnArgument(0);

		$part = $this->partService->createPart(1, '  Violin I ', '  ');

		$this->assertSame('Violin I', $part->getTitle());
		$this->assertNull($part->getDescription());
		$this->assertSame(1, $part->getScoreId());
	}

	public function testCreatePartRejectsEmptyAndLongTitle(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->partMapper->expects($this->never())->method('insert');
		foreach (['   ', str_repeat('a', 161)] as $title) {
			try {
				$this->partService->createPart(1, $title);
				$this->fail('Expected exception');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testCreatePartWithMissingScoreFails(): void {
		$this->scoreMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Score not found');
		$this->partService->createPart(1, 'Violin');
	}

	public function testUpdatePartKeepsUnprovidedFields(): void {
		$part = Part::fromParams(['scoreId' => 1, 'title' => 'Old', 'description' => 'Desc']);
		$this->partMapper->method('update')->willReturnArgument(0);

		$result = $this->partService->updatePart($part, 'New');

		$this->assertSame('New', $result['title']);
		$this->assertSame('Desc', $result['description']);
	}

	public function testDeletePartDeletesVariantsInTransaction(): void {
		$part = new Part();
		$this->partMapper->method('find')->willReturn($part);
		$this->db->expects($this->once())->method('beginTransaction');
		$this->partVariantMapper->expects($this->once())->method('deleteByPartId')->with(4);
		$this->partMapper->expects($this->once())->method('delete')->with($part);
		$this->db->expects($this->once())->method('commit');

		$this->partService->deletePart(4);
	}

	public function testDeletePartRollsBack(): void {
		$this->partMapper->method('find')->willReturn(new Part());
		$this->partVariantMapper->method('deleteByPartId')->willThrowException(new RuntimeException('x'));
		$this->db->expects($this->once())->method('rollBack');

		$this->expectException(RuntimeException::class);
		$this->partService->deletePart(4);
	}

	public function testDeleteMissingPartFails(): void {
		$this->partMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Part not found');
		$this->partService->deletePart(4);
	}

	public function testCreateConductorsScoreRejectsDuplicate(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->csMapper->method('findByScoreId')->willReturn(new ConductorsScore());
		$this->csMapper->expects($this->never())->method('insert');

		$this->expectExceptionMessage('The score already has a conductor\'s score');
		$this->csService->createConductorsScore(1);
	}

	public function testCreateConductorsScoreWithMissingScoreFails(): void {
		$this->scoreMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Score not found');
		$this->csService->createConductorsScore(1);
	}

	public function testCreateConductorsScore(): void {
		$this->scoreMapper->method('find')->willReturn(new Score());
		$this->csMapper->method('findByScoreId')->willReturn(null);
		$this->csMapper->method('insert')->willReturnArgument(0);

		$this->assertSame(1, $this->csService->createConductorsScore(1)->getScoreId());
	}

	public function testDeleteConductorsScoreDeletesVariantsInTransaction(): void {
		$cs = new ConductorsScore();
		$this->csMapper->method('find')->willReturn($cs);
		$this->csVariantMapper->expects($this->once())->method('deleteByConductorsScoreId')->with(2);
		$this->csMapper->expects($this->once())->method('delete')->with($cs);
		$this->db->expects($this->once())->method('commit');

		$this->csService->deleteConductorsScore(2);
	}

	public function testDeleteMissingConductorsScoreFails(): void {
		$this->csMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Conductor\'s score not found');
		$this->csService->deleteConductorsScore(2);
	}
}
