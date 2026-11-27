<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Db;

use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartVariant;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the serialization of the part and notation entities.
 */
final class PartAndNotationEntitiesTest extends TestCase {
	public function testClefSerialization(): void {
		$clef = Clef::fromParams(['sign' => 'percussion', 'line' => null, 'octave' => -1, 'title' => null]);
		$clef->setId(1);

		$this->assertSame(
			['id' => 1, 'sign' => 'percussion', 'line' => null, 'octave' => -1, 'title' => null],
			$clef->jsonSerialize(),
		);
	}

	public function testNotationPresetSerialization(): void {
		$preset = NotationPreset::fromParams([
			'clefId' => 2, 'transpositionDiatonic' => -1, 'transpositionChromatic' => -2,
			'transpositionOctave' => 0, 'title' => 'B flat', 'description' => null,
		]);
		$preset->setId(3);

		$this->assertSame([
			'id' => 3, 'clefId' => 2,
			'transposition' => ['diatonic' => -1, 'chromatic' => -2, 'octave' => 0],
			'title' => 'B flat', 'description' => null,
		], $preset->jsonSerialize());
	}

	public function testPartSerialization(): void {
		$part = Part::fromParams(['scoreId' => 4, 'title' => 'Violin', 'description' => 'd']);
		$part->setId(5);

		$this->assertSame(['id' => 5, 'scoreId' => 4, 'title' => 'Violin', 'description' => 'd'], $part->jsonSerialize());
	}

	public function testPartVariantSerialization(): void {
		$variant = PartVariant::fromParams([
			'partId' => 1, 'clefId' => 2, 'transpositionDiatonic' => 1, 'transpositionChromatic' => 2,
			'transpositionOctave' => 3, 'notationPresetId' => null, 'description' => null,
		]);
		$variant->setId(9);

		$this->assertSame([
			'id' => 9, 'partId' => 1, 'clefId' => 2,
			'transposition' => ['diatonic' => 1, 'chromatic' => 2, 'octave' => 3],
			'notationPresetId' => null, 'description' => null,
		], $variant->jsonSerialize());
	}

	public function testConductorsScoreSerialization(): void {
		$cs = ConductorsScore::fromParams(['scoreId' => 4]);
		$cs->setId(2);

		$this->assertSame(['id' => 2, 'scoreId' => 4], $cs->jsonSerialize());
	}

	public function testConductorsScoreVariantSerialization(): void {
		$variant = ConductorsScoreVariant::fromParams(['conductorsScoreId' => 2, 'type' => 'full', 'description' => null]);
		$variant->setId(6);

		$this->assertSame(
			['id' => 6, 'conductorsScoreId' => 2, 'type' => 'full', 'description' => null],
			$variant->jsonSerialize(),
		);
	}
}
