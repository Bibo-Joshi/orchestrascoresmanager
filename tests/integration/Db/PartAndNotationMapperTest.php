<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db;

use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariant;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;

/**
 * Integration tests for the part, conductor's score and notation mappers.
 *
 * Covers CRUD, the explicit and database level cascades and the foreign key rules.
 * These tests require a NextCloud environment and run in CI.
 */
final class PartAndNotationMapperTest extends MapperTestCase {
	private ScoreMapper $scoreMapper;
	private ClefMapper $clefMapper;
	private NotationPresetMapper $presetMapper;
	private PartMapper $partMapper;
	private PartVariantMapper $partVariantMapper;
	private ConductorsScoreMapper $csMapper;
	private ConductorsScoreVariantMapper $csVariantMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$this->presetMapper = new NotationPresetMapper($this->db);
		$this->partMapper = new PartMapper($this->db);
		$this->partVariantMapper = new PartVariantMapper($this->db);
		$this->clefMapper = new ClefMapper($this->db, $this->partVariantMapper, $this->presetMapper);
		$this->csMapper = new ConductorsScoreMapper($this->db);
		$this->csVariantMapper = new ConductorsScoreVariantMapper($this->db);
	}

	private function score(): Score {
		$score = new Score();
		$score->setTitle('Score');
		return $this->scoreMapper->insert($score);
	}

	private function clef(string $sign = 'G', ?int $line = 2, int $octave = 0, ?string $title = null): Clef {
		return $this->clefMapper->insert(Clef::fromParams(['sign' => $sign, 'line' => $line, 'octave' => $octave, 'title' => $title]));
	}

	private function preset(int $clefId, int $diatonic = 0, int $chromatic = 0, int $octave = 0): NotationPreset {
		return $this->presetMapper->insert(NotationPreset::fromParams([
			'clefId' => $clefId, 'transpositionDiatonic' => $diatonic, 'transpositionChromatic' => $chromatic,
			'transpositionOctave' => $octave, 'title' => 'Preset',
		]));
	}

	private function part(int $scoreId, string $title = 'Violin'): Part {
		return $this->partMapper->insert(Part::fromParams(['scoreId' => $scoreId, 'title' => $title]));
	}

	private function variant(int $partId, int $clefId, ?int $presetId = null): PartVariant {
		return $this->partVariantMapper->insert(PartVariant::fromParams([
			'partId' => $partId, 'clefId' => $clefId, 'transpositionDiatonic' => 0, 'transpositionChromatic' => 0,
			'transpositionOctave' => 0, 'notationPresetId' => $presetId,
		]));
	}

	public function testClefFindByNotationIsNullSafe(): void {
		$treble = $this->clef('G', 2);
		$titled = $this->clef('G', 2, 0, 'Titled');
		$percussion = $this->clef('percussion', null);

		$this->assertSame($treble->getId(), $this->clefMapper->findByNotation('G', 2, 0, null)?->getId());
		$this->assertSame($titled->getId(), $this->clefMapper->findByNotation('G', 2, 0, 'Titled')?->getId());
		$this->assertSame($percussion->getId(), $this->clefMapper->findByNotation('percussion', null, 0, null)?->getId());
		$this->assertNull($this->clefMapper->findByNotation('G', 2, 1, null));
		$this->assertNull($this->clefMapper->findByNotation('F', 4, 0, null));
	}

	public function testClefIsInUseByVariantOrPreset(): void {
		$byPreset = $this->clef('G', 1);
		$byVariant = $this->clef('G', 2);
		$unused = $this->clef('G', 3);
		$this->preset($byPreset->getId());
		$this->variant($this->part($this->score()->getId())->getId(), $byVariant->getId());

		$this->assertTrue($this->clefMapper->isInUse($byPreset->getId()));
		$this->assertTrue($this->clefMapper->isInUse($byVariant->getId()));
		$this->assertFalse($this->clefMapper->isInUse($unused->getId()));
	}

	public function testPresetFindByClefAndTransposition(): void {
		$clef = $this->clef();
		$a = $this->preset($clef->getId(), -1, -2, 0);
		$this->preset($clef->getId(), 0, 0, 0);

		$found = $this->presetMapper->findByClefAndTransposition($clef->getId(), -1, -2, 0);

		$this->assertCount(1, $found);
		$this->assertSame($a->getId(), $found[0]->getId());
		$this->assertSame([], $this->presetMapper->findByClefAndTransposition($clef->getId(), 5, 5, 0));
		$this->assertTrue($this->presetMapper->existsByClefId($clef->getId()));
	}

	public function testDeletingClefInUseIsRestricted(): void {
		$clef = $this->clef();
		$this->preset($clef->getId());

		$this->expectException(Exception::class);
		$this->clefMapper->delete($clef);
	}

	public function testDeletingClefUsedByVariantIsRestricted(): void {
		$clef = $this->clef();
		$this->variant($this->part($this->score()->getId())->getId(), $clef->getId());

		$this->expectException(Exception::class);
		$this->clefMapper->delete($clef);
	}

	public function testDeletingPresetSetsVariantLinkToNull(): void {
		$clef = $this->clef();
		$preset = $this->preset($clef->getId());
		$variant = $this->variant($this->part($this->score()->getId())->getId(), $clef->getId(), $preset->getId());

		$this->presetMapper->delete($preset);

		$reloaded = $this->partVariantMapper->find($variant->getId());
		$this->assertNull($reloaded->getNotationPresetId());
		$this->assertSame($clef->getId(), $reloaded->getClefId());
	}

	public function testUnlinkNotationPreset(): void {
		$clef = $this->clef();
		$preset = $this->preset($clef->getId());
		$variant = $this->variant($this->part($this->score()->getId())->getId(), $clef->getId(), $preset->getId());

		$this->partVariantMapper->unlinkNotationPreset($preset->getId());

		$this->assertNull($this->partVariantMapper->find($variant->getId())->getNotationPresetId());
	}

	public function testUnlinkMismatchingForPresetKeepsMatchingVariants(): void {
		$clef = $this->clef();
		$preset = $this->preset($clef->getId());
		$part = $this->part($this->score()->getId());
		$matching = $this->variant($part->getId(), $clef->getId(), $preset->getId());
		$stale = $this->variant($part->getId(), $clef->getId(), $preset->getId());
		$stale->setTranspositionDiatonic(3);
		$this->partVariantMapper->update($stale);

		$this->partVariantMapper->unlinkMismatchingForPreset($preset);

		$this->assertSame($preset->getId(), $this->partVariantMapper->find($matching->getId())->getNotationPresetId());
		$this->assertNull($this->partVariantMapper->find($stale->getId())->getNotationPresetId());
	}

	public function testDeletingScoreCascadesToPartsAndConductorsScoreButNotNotation(): void {
		$score = $this->score();
		$clef = $this->clef();
		$preset = $this->preset($clef->getId());
		$part = $this->part($score->getId());
		$variant = $this->variant($part->getId(), $clef->getId(), $preset->getId());
		$cs = $this->csMapper->insert(ConductorsScore::fromParams(['scoreId' => $score->getId()]));
		$csVariant = $this->csVariantMapper->insert(ConductorsScoreVariant::fromParams(['conductorsScoreId' => $cs->getId(), 'type' => 'full']));

		$this->scoreMapper->delete($score);

		foreach ([[$this->partMapper, $part], [$this->partVariantMapper, $variant], [$this->csMapper, $cs], [$this->csVariantMapper, $csVariant]] as [$mapper, $entity]) {
			try {
				$mapper->find($entity->getId());
				$this->fail('Expected ' . $entity::class . ' to be deleted');
			} catch (DoesNotExistException) {
				$this->addToAssertionCount(1);
			}
		}
		$this->assertSame($clef->getId(), $this->clefMapper->find($clef->getId())->getId());
		$this->assertSame($preset->getId(), $this->presetMapper->find($preset->getId())->getId());
	}

	public function testExplicitDeleteByScoreId(): void {
		$score = $this->score();
		$other = $this->score();
		$clef = $this->clef();
		$part = $this->part($score->getId());
		$otherPart = $this->part($other->getId());
		$this->variant($part->getId(), $clef->getId());
		$kept = $this->variant($otherPart->getId(), $clef->getId());
		$cs = $this->csMapper->insert(ConductorsScore::fromParams(['scoreId' => $score->getId()]));
		$this->csVariantMapper->insert(ConductorsScoreVariant::fromParams(['conductorsScoreId' => $cs->getId(), 'type' => 'full']));

		$this->partVariantMapper->deleteByScoreId($score->getId());
		$this->partMapper->deleteByScoreId($score->getId());
		$this->csVariantMapper->deleteByScoreId($score->getId());
		$this->csMapper->deleteByScoreId($score->getId());

		$this->assertSame([], $this->partMapper->findByScoreId($score->getId()));
		$this->assertNull($this->csMapper->findByScoreId($score->getId()));
		$this->assertSame([], $this->csVariantMapper->findByConductorsScoreId($cs->getId()));
		$this->assertSame([$kept->getId()], array_map(static fn ($v) => $v->getId(), $this->partVariantMapper->findByPartId($otherPart->getId())));
		$this->assertCount(1, $this->partMapper->findByScoreId($other->getId()));
	}

	public function testDeletingPartCascadesToVariants(): void {
		$clef = $this->clef();
		$part = $this->part($this->score()->getId());
		$variant = $this->variant($part->getId(), $clef->getId());

		$this->partMapper->delete($part);

		$this->expectException(DoesNotExistException::class);
		$this->partVariantMapper->find($variant->getId());
	}

	public function testDeleteByPartId(): void {
		$clef = $this->clef();
		$part = $this->part($this->score()->getId());
		$this->variant($part->getId(), $clef->getId());

		$this->partVariantMapper->deleteByPartId($part->getId());

		$this->assertSame([], $this->partVariantMapper->findByPartId($part->getId()));
		$this->assertFalse($this->partVariantMapper->existsByClefId($clef->getId()));
	}

	public function testConductorsScoreIsUniquePerScore(): void {
		$score = $this->score();
		$this->csMapper->insert(ConductorsScore::fromParams(['scoreId' => $score->getId()]));

		$this->expectException(Exception::class);
		$this->csMapper->insert(ConductorsScore::fromParams(['scoreId' => $score->getId()]));
	}

	public function testDeletingConductorsScoreCascadesToVariants(): void {
		$cs = $this->csMapper->insert(ConductorsScore::fromParams(['scoreId' => $this->score()->getId()]));
		$variant = $this->csVariantMapper->insert(ConductorsScoreVariant::fromParams(['conductorsScoreId' => $cs->getId(), 'type' => 'condensed']));

		$this->csMapper->delete($cs);

		$this->expectException(DoesNotExistException::class);
		$this->csVariantMapper->find($variant->getId());
	}

	public function testForeignKeysRejectUnknownParents(): void {
		$this->expectException(Exception::class);
		$this->partMapper->insert(Part::fromParams(['scoreId' => 999999999, 'title' => 'Orphan']));
	}
}
