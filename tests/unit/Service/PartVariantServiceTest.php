<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\NotationPreset;
use OCA\OrchestraScoresManager\Db\NotationPresetMapper;
use OCA\OrchestraScoresManager\Db\Part;
use OCA\OrchestraScoresManager\Db\PartMapper;
use OCA\OrchestraScoresManager\Db\PartVariant;
use OCA\OrchestraScoresManager\Db\PartVariantMapper;
use OCA\OrchestraScoresManager\Policy\PartVariantPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\ClefService;
use OCA\OrchestraScoresManager\Service\Exceptions\PermissionDeniedException;
use OCA\OrchestraScoresManager\Service\PartVariantService;
use OCA\OrchestraScoresManager\Service\TranspositionValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for PartVariantService, in particular the notation resolution.
 */
final class PartVariantServiceTest extends TestCase {
	private PartVariantMapper&MockObject $variantMapper;
	private PartMapper&MockObject $partMapper;
	private NotationPresetMapper&MockObject $presetMapper;
	private ClefService&MockObject $clefService;
	private IDBConnection&MockObject $db;
	private AuthorizationService&MockObject $authorizationService;
	private PartVariantService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->variantMapper = $this->createMock(PartVariantMapper::class);
		$this->partMapper = $this->createMock(PartMapper::class);
		$this->presetMapper = $this->createMock(NotationPresetMapper::class);
		$this->clefService = $this->createMock(ClefService::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new PartVariantService(
			$this->variantMapper,
			$this->partMapper,
			$this->presetMapper,
			$this->clefService,
			new TranspositionValidator($l10n),
			$this->db,
			$this->authorizationService,
			$this->createMock(PartVariantPolicy::class),
			$l10n,
		);

		$this->variantMapper->method('insert')->willReturnArgument(0);
		$this->variantMapper->method('update')->willReturnArgument(0);
	}

	private function preset(int $id, int $clefId, int $dia = 0, int $chr = 0, int $oct = 0): NotationPreset {
		$preset = new NotationPreset();
		$preset->setId($id);
		$preset->setClefId($clefId);
		$preset->setTranspositionDiatonic($dia);
		$preset->setTranspositionChromatic($chr);
		$preset->setTranspositionOctave($oct);
		return $preset;
	}

	private function clef(int $id): Clef {
		$clef = new Clef();
		$clef->setId($id);
		return $clef;
	}

	private function variant(int $clefId, int $dia, int $chr, int $oct, ?int $presetId): PartVariant {
		$variant = new PartVariant();
		$variant->setId(5);
		$variant->setPartId(1);
		$variant->setClefId($clefId);
		$variant->setTranspositionDiatonic($dia);
		$variant->setTranspositionChromatic($chr);
		$variant->setTranspositionOctave($oct);
		$variant->setNotationPresetId($presetId);
		return $variant;
	}

	private function expectPartExists(): void {
		$this->partMapper->method('find')->willReturn(new Part());
	}

	public function testCreateWithPresetCopiesNotationAndLinks(): void {
		$this->expectPartExists();
		$this->presetMapper->method('find')->with(7)->willReturn($this->preset(7, 3, 1, 2, 0));
		$this->db->expects($this->once())->method('commit');

		$variant = $this->service->createPartVariant(1, 7, null, null, '  desc ');

		$this->assertSame(3, $variant->getClefId());
		$this->assertSame(1, $variant->getTranspositionDiatonic());
		$this->assertSame(2, $variant->getTranspositionChromatic());
		$this->assertSame(7, $variant->getNotationPresetId());
		$this->assertSame('desc', $variant->getDescription());
	}

	public function testCreateWithMissingPresetFails(): void {
		$this->expectPartExists();
		$this->presetMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->db->expects($this->once())->method('rollBack');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Notation preset not found');
		$this->service->createPartVariant(1, 99);
	}

	public function testCreateWithMissingPartFails(): void {
		$this->partMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->variantMapper->expects($this->never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Part not found');
		$this->service->createPartVariant(1, 7);
	}

	public function testCreateWithPresetAndClefFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, 7, ['id' => 1], null);
	}

	public function testCreateWithPresetAndTranspositionFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, 7, null, ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);
	}

	public function testCreateWithNothingFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1);
	}

	public function testCreateWithClefOnlyFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, null, ['id' => 1], null);
	}

	public function testCreateWithTranspositionOnlyFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, null, null, ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);
	}

	public function testCreateWithUnknownClefIdFails(): void {
		$this->expectPartExists();
		$this->clefService->method('findClefEntity')->willThrowException(new InvalidArgumentException('Clef not found'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Clef not found');
		$this->service->createPartVariant(1, null, ['id' => 99], ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);
	}

	public function testCreateWithClefIdAndOtherKeysFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, null, ['id' => 1, 'sign' => 'G'], ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);
	}

	public function testCreateWithClefWithoutIdOrSignFails(): void {
		$this->expectPartExists();
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, null, ['line' => 2], ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);
	}

	/**
	 * @return array<string, array{array<array-key, mixed>}>
	 */
	public static function invalidTranspositions(): array {
		return [
			'missing key' => [['diatonic' => 0, 'chromatic' => 0]],
			'string value' => [['diatonic' => '1', 'chromatic' => 0, 'octave' => 0]],
			'out of range' => [['diatonic' => 0, 'chromatic' => 0, 'octave' => 9]],
		];
	}

	/**
	 * @param array<array-key, mixed> $transposition
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('invalidTranspositions')]
	public function testCreateWithInvalidTranspositionFails(array $transposition): void {
		$this->expectPartExists();
		$this->clefService->method('findClefEntity')->willReturn($this->clef(1));
		$this->expectException(InvalidArgumentException::class);
		$this->service->createPartVariant(1, null, ['id' => 1], $transposition);
	}

	public function testCreateWithExistingClefAndNoMatchingPresetLeavesLinkNull(): void {
		$this->expectPartExists();
		$this->clefService->method('findClefEntity')->with(4)->willReturn($this->clef(4));
		$this->presetMapper->method('findByClefAndTransposition')->with(4, -1, -2, 0)->willReturn([]);

		$variant = $this->service->createPartVariant(1, null, ['id' => 4], ['diatonic' => -1, 'chromatic' => -2, 'octave' => 0]);

		$this->assertSame(4, $variant->getClefId());
		$this->assertNull($variant->getNotationPresetId());
	}

	public function testCreateWithNewClefCreatesClefAndLinksUniquePreset(): void {
		$this->expectPartExists();
		$this->clefService->expects($this->once())->method('findOrCreateClef')
			->with('G', 2, 0, 'Treble')->willReturn($this->clef(8));
		$this->presetMapper->method('findByClefAndTransposition')->with(8, 0, 0, 0)
			->willReturn([$this->preset(3, 8)]);

		$variant = $this->service->createPartVariant(
			1,
			null,
			['sign' => 'G', 'line' => 2, 'octave' => 0, 'title' => 'Treble'],
			['diatonic' => 0, 'chromatic' => 0, 'octave' => 0],
		);

		$this->assertSame(8, $variant->getClefId());
		$this->assertSame(3, $variant->getNotationPresetId());
	}

	public function testCreateWithSeveralMatchingPresetsLeavesLinkNull(): void {
		$this->expectPartExists();
		$this->clefService->method('findClefEntity')->willReturn($this->clef(2));
		$this->presetMapper->method('findByClefAndTransposition')
			->willReturn([$this->preset(1, 2), $this->preset(2, 2)]);

		$variant = $this->service->createPartVariant(1, null, ['id' => 2], ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);

		$this->assertNull($variant->getNotationPresetId());
	}

	public function testCreateRollsBackOnFailure(): void {
		$this->expectPartExists();
		$this->presetMapper->method('find')->willReturn($this->preset(1, 1));
		$variantMapper = $this->createMock(PartVariantMapper::class);
		$variantMapper->method('insert')->willThrowException(new RuntimeException('db'));
		$service = new PartVariantService(
			$variantMapper,
			$this->partMapper,
			$this->presetMapper,
			$this->clefService,
			$this->createMock(TranspositionValidator::class),
			$this->db,
			$this->authorizationService,
			$this->createMock(PartVariantPolicy::class),
			$this->createMock(IL10N::class),
		);
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$this->expectException(RuntimeException::class);
		$service->createPartVariant(1, 1);
	}

	public function testUpdateWithPresetReplacesNotationAndLink(): void {
		$variant = $this->variant(1, 0, 0, 0, null);
		$this->presetMapper->method('find')->with(7)->willReturn($this->preset(7, 3, 2, 3, 1));

		$result = $this->service->updatePartVariant($variant, 7);

		$this->assertSame(3, $result['clefId']);
		$this->assertSame(['diatonic' => 2, 'chromatic' => 3, 'octave' => 1], $result['transposition']);
		$this->assertSame(7, $result['notationPresetId']);
	}

	public function testUpdateWithPresetAndClefFails(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->updatePartVariant($this->variant(1, 0, 0, 0, null), 7, ['id' => 2]);
	}

	public function testUpdateWithoutNotationKeepsNotationAndLink(): void {
		$variant = $this->variant(1, 2, 3, 0, 9);
		$this->presetMapper->expects($this->never())->method('findByClefAndTransposition');

		$result = $this->service->updatePartVariant($variant, null, null, null, 'new');

		$this->assertSame(9, $result['notationPresetId']);
		$this->assertSame(1, $result['clefId']);
		$this->assertSame('new', $result['description']);
	}

	public function testUpdateEmptyDescriptionClearsIt(): void {
		$variant = $this->variant(1, 0, 0, 0, null);
		$variant->setDescription('old');

		$result = $this->service->updatePartVariant($variant, null, null, null, '  ');

		$this->assertNull($result['description']);
	}

	public function testUpdateTranspositionOnlyRemovesStaleLinkAndLinksNewUniqueMatch(): void {
		$variant = $this->variant(1, 0, 0, 0, 9);
		$this->presetMapper->method('findByClefAndTransposition')->with(1, -2, -3, 0)
			->willReturn([$this->preset(4, 1, -2, -3, 0)]);

		$result = $this->service->updatePartVariant($variant, null, null, ['diatonic' => -2, 'chromatic' => -3, 'octave' => 0]);

		$this->assertSame(4, $result['notationPresetId']);
		$this->assertSame(1, $result['clefId']);
	}

	public function testUpdateRemovesStaleLinkWhenNoPresetMatches(): void {
		$variant = $this->variant(1, 0, 0, 0, 9);
		$this->presetMapper->method('findByClefAndTransposition')->willReturn([]);

		$result = $this->service->updatePartVariant($variant, null, null, ['diatonic' => 1, 'chromatic' => 1, 'octave' => 0]);

		$this->assertNull($result['notationPresetId']);
	}

	public function testUpdateKeepsStoredPresetThatStillMatches(): void {
		$variant = $this->variant(1, 0, 0, 0, 9);
		$this->presetMapper->method('findByClefAndTransposition')
			->willReturn([$this->preset(8, 1), $this->preset(9, 1)]);

		$result = $this->service->updatePartVariant($variant, null, null, ['diatonic' => 0, 'chromatic' => 0, 'octave' => 0]);

		$this->assertSame(9, $result['notationPresetId']);
	}

	public function testUpdateStaleLinkWithSeveralNewMatchesBecomesNull(): void {
		$variant = $this->variant(1, 0, 0, 0, 9);
		$this->presetMapper->method('findByClefAndTransposition')
			->willReturn([$this->preset(1, 1), $this->preset(2, 1)]);

		$result = $this->service->updatePartVariant($variant, null, null, ['diatonic' => 0, 'chromatic' => 2, 'octave' => 0]);

		$this->assertNull($result['notationPresetId']);
	}

	public function testUpdateWithNewClefMergesStoredTranspositionAndCreatesClef(): void {
		$variant = $this->variant(1, 1, 2, 0, 9);
		$this->clefService->expects($this->once())->method('findOrCreateClef')
			->with('F', 4, 0, null)->willReturn($this->clef(6));
		$this->presetMapper->method('findByClefAndTransposition')->with(6, 1, 2, 0)->willReturn([]);

		$result = $this->service->updatePartVariant($variant, null, ['sign' => 'F', 'line' => 4]);

		$this->assertSame(6, $result['clefId']);
		$this->assertSame(['diatonic' => 1, 'chromatic' => 2, 'octave' => 0], $result['transposition']);
		$this->assertNull($result['notationPresetId']);
	}

	public function testUpdateRollsBackOnInvalidInput(): void {
		$this->db->expects($this->once())->method('rollBack');
		$this->expectException(InvalidArgumentException::class);
		$this->service->updatePartVariant($this->variant(1, 0, 0, 0, null), null, null, ['diatonic' => 0]);
	}

	public function testDeleteMissingVariantFails(): void {
		$this->variantMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Part variant not found');
		$this->service->deletePartVariant(1);
	}

	public function testDeleteDeletesOnlyTheVariant(): void {
		$variant = $this->variant(1, 0, 0, 0, null);
		$this->variantMapper->method('find')->willReturn($variant);
		$this->variantMapper->expects($this->once())->method('delete')->with($variant);
		$this->presetMapper->expects($this->never())->method('delete');

		$this->service->deletePartVariant(5);
	}

	public function testUnauthorizedCreateDoesNotTouchDatabase(): void {
		$this->authorizationService->method('authorizePolicy')->willThrowException(new PermissionDeniedException());
		$this->partMapper->expects($this->never())->method('find');

		$this->expectException(PermissionDeniedException::class);
		$this->service->createPartVariant(1, 7);
	}
}
