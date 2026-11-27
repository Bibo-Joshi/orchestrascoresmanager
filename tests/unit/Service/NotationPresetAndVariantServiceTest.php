<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScore;
use OCA\OrchestraScoresManager\Db\ConductorsScoreMapper;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariant;
use OCA\OrchestraScoresManager\Db\ConductorsScoreVariantMapper;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Policy\ConductorsScoreVariantPolicy;
use OCA\OrchestraScoresManager\Policy\NotationPresetPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\ConductorsScoreVariantService;
use OCA\OrchestraScoresManager\Service\NotationPresetService;
use OCA\OrchestraScoresManager\Service\TranspositionValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotationPresetService and ConductorsScoreVariantService.
 */
final class NotationPresetAndVariantServiceTest extends TestCase {
	private NotationPresetMapper&MockObject $presetMapper;
	private PartVariantMapper&MockObject $partVariantMapper;
	private ClefMapper&MockObject $clefMapper;
	private IDBConnection&MockObject $db;
	private ConductorsScoreVariantMapper&MockObject $csVariantMapper;
	private ConductorsScoreMapper&MockObject $csMapper;
	private NotationPresetService $presetService;
	private ConductorsScoreVariantService $csVariantService;

	protected function setUp(): void {
		parent::setUp();
		$this->presetMapper = $this->createMock(NotationPresetMapper::class);
		$this->partVariantMapper = $this->createMock(PartVariantMapper::class);
		$this->clefMapper = $this->createMock(ClefMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->csVariantMapper = $this->createMock(ConductorsScoreVariantMapper::class);
		$this->csMapper = $this->createMock(ConductorsScoreMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));
		$auth = $this->createMock(AuthorizationService::class);

		$this->presetService = new NotationPresetService(
			$this->presetMapper, $this->partVariantMapper, $this->clefMapper, new TranspositionValidator($l10n),
			$this->db, $auth, $this->createMock(NotationPresetPolicy::class), $l10n,
		);
		$this->csVariantService = new ConductorsScoreVariantService(
			$this->csVariantMapper, $this->csMapper, $auth,
			$this->createMock(ConductorsScoreVariantPolicy::class), $l10n,
		);
	}

	private function preset(): NotationPreset {
		$preset = NotationPreset::fromParams([
			'clefId' => 1, 'transpositionDiatonic' => 0, 'transpositionChromatic' => 0,
			'transpositionOctave' => 0, 'title' => 'C',
		]);
		$preset->setId(3);
		return $preset;
	}

	private const T = ['diatonic' => -1, 'chromatic' => -2, 'octave' => 0];

	public function testCreatePresetWithMissingClefFails(): void {
		$this->clefMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Clef not found');
		$this->presetService->createNotationPreset(9, self::T, 'B flat');
	}

	public function testCreatePresetValidatesTitleAndTransposition(): void {
		$this->clefMapper->method('find')->willReturn(new Clef());
		$this->presetMapper->expects($this->never())->method('insert');
		$this->expectException(InvalidArgumentException::class);
		$this->presetService->createNotationPreset(1, self::T, '  ');
	}

	public function testCreatePreset(): void {
		$this->clefMapper->method('find')->willReturn(new Clef());
		$this->presetMapper->method('insert')->willReturnArgument(0);

		$preset = $this->presetService->createNotationPreset(1, self::T, ' B flat ', '');

		$this->assertSame('B flat', $preset->getTitle());
		$this->assertSame(-2, $preset->getTranspositionChromatic());
		$this->assertNull($preset->getDescription());
	}

	public function testUpdateNotationUnlinksMismatchingVariants(): void {
		$this->clefMapper->method('find')->willReturn(new Clef());
		$this->presetMapper->method('update')->willReturnArgument(0);
		$this->partVariantMapper->expects($this->once())->method('unlinkMismatchingForPreset');
		$this->db->expects($this->once())->method('commit');

		$result = $this->presetService->updateNotationPreset($this->preset(), 2, self::T);

		$this->assertSame(2, $result['clefId']);
	}

	public function testUpdateTitleOnlyDoesNotUnlink(): void {
		$this->presetMapper->method('update')->willReturnArgument(0);
		$this->partVariantMapper->expects($this->never())->method('unlinkMismatchingForPreset');

		$result = $this->presetService->updateNotationPreset($this->preset(), null, null, 'New');

		$this->assertSame('New', $result['title']);
	}

	public function testDeleteUnlinksVariantsBeforeDeleting(): void {
		$preset = $this->preset();
		$this->presetMapper->method('find')->willReturn($preset);
		$order = [];
		$this->partVariantMapper->method('unlinkNotationPreset')->willReturnCallback(function (int $id) use (&$order): void {
			$order[] = "unlink$id";
		});
		$this->presetMapper->method('delete')->willReturnCallback(function ($p) use (&$order, $preset) {
			$order[] = 'delete';
			return $preset;
		});

		$this->presetService->deleteNotationPreset(3);

		$this->assertSame(['unlink3', 'delete'], $order);
	}

	public function testDeleteMissingPresetFails(): void {
		$this->presetMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Notation preset not found');
		$this->presetService->deleteNotationPreset(3);
	}

	public function testCreateConductorsScoreVariantRejectsUnsupportedType(): void {
		$this->csMapper->method('find')->willReturn(new ConductorsScore());
		$this->csVariantMapper->expects($this->never())->method('insert');
		$this->expectExceptionMessage('Unsupported conductor\'s score variant type "huge"');
		$this->csVariantService->createConductorsScoreVariant(1, 'huge');
	}

	public function testCreateConductorsScoreVariantWithMissingParentFails(): void {
		$this->csMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Conductor\'s score not found');
		$this->csVariantService->createConductorsScoreVariant(1, 'full');
	}

	public function testCreateConductorsScoreVariant(): void {
		$this->csMapper->method('find')->willReturn(new ConductorsScore());
		$this->csVariantMapper->method('insert')->willReturnArgument(0);

		$variant = $this->csVariantService->createConductorsScoreVariant(1, 'condensed', ' d ');

		$this->assertSame('condensed', $variant->getType());
		$this->assertSame('d', $variant->getDescription());
	}

	public function testUpdateConductorsScoreVariantValidatesType(): void {
		$variant = ConductorsScoreVariant::fromParams(['conductorsScoreId' => 1, 'type' => 'full']);
		$this->csVariantMapper->expects($this->never())->method('update');
		$this->expectException(InvalidArgumentException::class);
		$this->csVariantService->updateConductorsScoreVariant($variant, 'bogus');
	}

	public function testUpdateConductorsScoreVariantKeepsUnprovidedFields(): void {
		$variant = ConductorsScoreVariant::fromParams(['conductorsScoreId' => 1, 'type' => 'full', 'description' => 'x']);
		$this->csVariantMapper->method('update')->willReturnArgument(0);

		$result = $this->csVariantService->updateConductorsScoreVariant($variant, 'condensed');

		$this->assertSame('condensed', $result['type']);
		$this->assertSame('x', $result['description']);
	}
}
