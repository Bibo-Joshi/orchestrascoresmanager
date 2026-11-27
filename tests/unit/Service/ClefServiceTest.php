<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Policy\ClefPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\ClefService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ClefService.
 */
final class ClefServiceTest extends TestCase {
	private ClefMapper&MockObject $clefMapper;
	private ClefService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->clefMapper = $this->createMock(ClefMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));
		$this->service = new ClefService(
			$this->clefMapper,
			$this->createMock(AuthorizationService::class),
			$this->createMock(ClefPolicy::class),
			$l10n,
		);
	}

	private function clef(int $id, string $sign = 'G', ?int $line = 2, int $octave = 0, ?string $title = null): Clef {
		$clef = new Clef();
		$clef->setId($id);
		$clef->setSign($sign);
		$clef->setLine($line);
		$clef->setOctave($octave);
		$clef->setTitle($title);
		return $clef;
	}

	/**
	 * @return array<string, array{string, ?int, int, ?string}>
	 */
	public static function invalidClefs(): array {
		return [
			'unknown sign' => ['X', 2, 0, null],
			'G without line' => ['G', null, 0, null],
			'G line too high' => ['G', 6, 0, null],
			'C line zero' => ['C', 0, 0, null],
			'percussion with line' => ['percussion', 1, 0, null],
			'TAB with line' => ['TAB', 3, 0, null],
			'octave too large' => ['G', 2, 3, null],
			'title too long' => ['G', 2, 0, str_repeat('a', 161)],
		];
	}

	#[DataProvider('invalidClefs')]
	public function testFindOrCreateRejectsInvalidInput(string $sign, ?int $line, int $octave, ?string $title): void {
		$this->clefMapper->expects($this->never())->method('insert');
		$this->expectException(InvalidArgumentException::class);
		$this->service->findOrCreateClef($sign, $line, $octave, $title);
	}

	public function testUnsupportedSignMessage(): void {
		$this->expectExceptionMessage('Unsupported clef sign "X"');
		$this->service->findOrCreateClef('X', 2);
	}

	public function testFindOrCreateReusesIdenticalClef(): void {
		$existing = $this->clef(3, 'G', 2, 0, 'Treble');
		$this->clefMapper->method('findByNotation')->with('G', 2, 0, 'Treble')->willReturn($existing);
		$this->clefMapper->expects($this->never())->method('insert');

		$this->assertSame($existing, $this->service->findOrCreateClef('G', 2, 0, ' Treble '));
	}

	public function testFindOrCreateCreatesClefWithDifferingTitle(): void {
		$this->clefMapper->method('findByNotation')->with('G', 2, 0, 'Other')->willReturn(null);
		$this->clefMapper->expects($this->once())->method('insert')->willReturnArgument(0);

		$clef = $this->service->findOrCreateClef('G', 2, 0, 'Other');

		$this->assertSame('Other', $clef->getTitle());
	}

	public function testFindOrCreateNormalizesEmptyTitleToNull(): void {
		$this->clefMapper->expects($this->once())->method('findByNotation')->with('percussion', null, 0, null)->willReturn(null);
		$this->clefMapper->method('insert')->willReturnArgument(0);

		$this->assertNull($this->service->findOrCreateClef('percussion', null, 0, '   ')->getTitle());
	}

	public function testUpdateToLinelessSignClearsLine(): void {
		$clef = $this->clef(1);
		$this->clefMapper->method('findByNotation')->willReturn(null);
		$this->clefMapper->method('update')->willReturnArgument(0);

		$result = $this->service->updateClef($clef, 'percussion');

		$this->assertSame('percussion', $result['sign']);
		$this->assertNull($result['line']);
	}

	public function testUpdateRejectsDuplicate(): void {
		$this->clefMapper->method('findByNotation')->willReturn($this->clef(2));
		$this->clefMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('An identical clef already exists');
		$this->service->updateClef($this->clef(1, 'G', 1), null, 2);
	}

	public function testUpdateAllowsMatchingItself(): void {
		$clef = $this->clef(1);
		$this->clefMapper->method('findByNotation')->willReturn($clef);
		$this->clefMapper->method('update')->willReturnArgument(0);

		$result = $this->service->updateClef($clef, null, null, null, 'New');

		$this->assertSame('New', $result['title']);
	}

	public function testDeleteMissingClefFails(): void {
		$this->clefMapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->expectExceptionMessage('Clef not found');
		$this->service->deleteClef(1);
	}

	public function testDeleteClefInUseFails(): void {
		$this->clefMapper->method('find')->willReturn($this->clef(1));
		$this->clefMapper->method('isInUse')->with(1)->willReturn(true);
		$this->clefMapper->expects($this->never())->method('delete');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Clef is still in use');
		$this->service->deleteClef(1);
	}

	public function testDeleteUnusedClef(): void {
		$clef = $this->clef(1);
		$this->clefMapper->method('find')->willReturn($clef);
		$this->clefMapper->method('isInUse')->willReturn(false);
		$this->clefMapper->expects($this->once())->method('delete')->with($clef);

		$this->service->deleteClef(1);
	}
}
