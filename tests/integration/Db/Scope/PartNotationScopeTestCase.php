<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\integration\Db\Scope;

use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\ScoreTagLinkMapper;
use OCA\OrchestraScoresManager\Db\TagMapper;

/**
 * Base for the scope tests of the part and notation entities, which share the same parent rows.
 */
abstract class PartNotationScopeTestCase extends ScopePolicyTestCase {
	protected function insertScore(): Score {
		$scoreMapper = new ScoreMapper(
			$this->db,
			new ScoreTagLinkMapper($this->db),
			new TagMapper($this->db),
			new ScoreBookScoreLinkMapper($this->db),
		);
		$score = new Score();
		$score->setTitle('Score');
		return $scoreMapper->insert($score);
	}

	protected function insertClef(string $sign = 'G', ?int $line = 2): Clef {
		return (new ClefMapper(
			$this->db,
			new PartVariantMapper($this->db),
			new NotationPresetMapper($this->db),
		))->insert(Clef::fromParams([
			'sign' => $sign, 'line' => $line, 'octave' => 0, 'title' => null,
		]));
	}

	protected function insertPart(): Part {
		return (new PartMapper($this->db))->insert(Part::fromParams([
			'scoreId' => $this->insertScore()->getId(), 'title' => 'Part',
		]));
	}

	protected function insertConductorsScore(): ConductorsScore {
		return (new ConductorsScoreMapper($this->db))->insert(ConductorsScore::fromParams([
			'scoreId' => $this->insertScore()->getId(),
		]));
	}
}
