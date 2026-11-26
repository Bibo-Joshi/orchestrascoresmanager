<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Service\SetlistTemplateValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetlistTemplateValidatorTest extends TestCase {
	private SetlistTemplateValidator $validator;

	protected function setUp(): void {
		$this->validator = new SetlistTemplateValidator();
	}

	private static function validTemplate(): array {
		return [
			'name' => 'Stage',
			'description' => null,
			'fontSizes' => ['title' => 16, 'scoreRow' => 8],
			'headerInfo' => [
				'entries' => ['date', 'startTime', 'endTime', 'playDuration', 'breaksDuration', 'overallDuration', 'folderCollection'],
				'layout' => 'table',
			],
			'margin' => 8,
			'cellPadding' => 1.5,
			'rules' => [[
				'id' => 'duet',
				'order' => 0,
				'label' => null,
				'expression' => ['all' => [['fact' => 'setlist.tags', 'operator' => 'contains', 'value' => 'duett']]],
				'backgroundColor' => '#FFE2B8',
				'cellPadding' => null,
			]],
		];
	}

	public function testValidTemplate(): void {
		$validated = $this->validator->validate(self::validTemplate());
		$this->assertSame('Stage', $validated['name']);
		$this->assertSame(8.0, $validated['config']['margin']);
		$this->assertCount(1, $validated['config']['rules']);
		$this->assertSame(0, $validated['config']['rules'][0]['order']);
	}

	public function testNullDescriptionAndRuleFormattingAreOptional(): void {
		$input = self::validTemplate();
		$input['rules'][0] = [
			'id' => 'break', 'order' => 2, 'expression' => ['all' => []],
		];
		$this->assertSame(null, $this->validator->validate($input)['description']);
	}

	public function testMoreThanThirtyRulesAreAllowed(): void {
		$input = self::validTemplate();
		$rule = $input['rules'][0];
		$input['rules'] = [];

		for ($i = 0; $i < 40; $i++) {
			$input['rules'][] = array_replace($rule, [
				'id' => 'rule-' . $i,
				'order' => $i,
			]);
		}

		$validated = $this->validator->validate($input);

		$this->assertCount(40, $validated['config']['rules']);
	}

	public function testFullHeaderInfo(): void {
		$validated = $this->validator->validate(self::validTemplate());

		$this->assertSame([
			'entries' => ['date', 'startTime', 'endTime', 'playDuration', 'breaksDuration', 'overallDuration', 'folderCollection'],
			'layout' => 'table',
		], $validated['config']['headerInfo']);
	}

	public function testCompactHeaderInfo(): void {
		$input = self::validTemplate();
		$input['headerInfo'] = [
			'entries' => ['date', 'startTime', 'overallDuration'],
			'layout' => 'subtitle',
		];

		$this->assertSame($input['headerInfo'], $this->validator->validate($input)['config']['headerInfo']);
	}

	public function testCustomHeaderInfo(): void {
		$input = self::validTemplate();
		$input['headerInfo'] = [
			'entries' => ['folderCollection', 'overallDuration', 'date'],
			'layout' => 'subtitle',
		];

		$this->assertSame($input['headerInfo'], $this->validator->validate($input)['config']['headerInfo']);
	}

	public static function invalidTemplates(): iterable {

		yield 'font sizes as string' => [static function (array &$i): void {
			$i['fontSizes'] = 'invalid';
		}];

		yield 'font sizes as list' => [static function (array &$i): void {
			$i['fontSizes'] = [12, 16];
		}];

		yield 'rules as string' => [static function (array &$i): void {
			$i['rules'] = 'invalid';
		}];

		yield 'rules as associative array' => [static function (array &$i): void {
			$i['rules'] = ['rule' => []];
		}];

		yield 'invalid header layout' => [static function (array &$i): void {
			$i['headerInfo'] = ['entries' => ['date'], 'layout' => 'invalid'];
		}];
		yield 'unknown header entry' => [static function (array &$i): void {
			$i['headerInfo'] = ['entries' => ['unknown'], 'layout' => 'table'];
		}];
		yield 'duplicate header entry' => [static function (array &$i): void {
			$i['headerInfo'] = ['entries' => ['date', 'date'], 'layout' => 'table'];
		}];
		yield 'invalid header entries' => [static function (array &$i): void {
			$i['headerInfo'] = ['entries' => 'date', 'layout' => 'table'];
		}];
		yield 'missing header layout' => [static function (array &$i): void {
			$i['headerInfo'] = ['entries' => ['date']];
		}];
		yield 'missing header info' => [static function (array &$i): void {
			unset($i['headerInfo']);
		}];
		yield 'null header info' => [static function (array &$i): void {
			$i['headerInfo'] = null;
		}];

		yield 'blank name' => [static function (array &$i): void {
			$i['name'] = '   ';
		}];
		yield 'non-string description' => [static function (array &$i): void {
			$i['description'] = ['invalid'];
		}];
		yield 'rule is not an object' => [static function (array &$i): void {
			$i['rules'][0] = 'not-an-object';
		}];
		yield 'invalid rule label' => [static function (array &$i): void {
			$i['rules'][0]['label'] = 3;
		}];
		yield 'bad rule cell padding' => [static function (array &$i): void {
			$i['rules'][0]['cellPadding'] = 25;
		}];
		yield 'oversized config JSON' => [static function (array &$i): void {
			$i['rules'][0]['expression'] = ['data' => str_repeat('x', 60000)];
		}];
		yield 'unknown font key' => [static function (array &$i): void {
			$i['fontSizes']['strange'] = 10;
		}];
		yield 'string font size' => [static function (array &$i): void {
			$i['fontSizes']['title'] = '12';
		}];
		yield 'small font size' => [static function (array &$i): void {
			$i['fontSizes']['title'] = 2;
		}];
		yield 'negative margin' => [static function (array &$i): void {
			$i['margin'] = -1;
		}];
		yield 'string padding' => [static function (array &$i): void {
			$i['cellPadding'] = '1.5';
		}];
		yield 'duplicate order' => [static function (array &$i): void {
			$i['rules'][] = ['id' => 'other', 'order' => 0, 'expression' => []];
		}];
		yield 'duplicate ID' => [static function (array &$i): void {
			$i['rules'][] = ['id' => 'duet', 'order' => 1, 'expression' => []];
		}];
		yield 'bad color' => [static function (array &$i): void {
			$i['rules'][0]['backgroundColor'] = 'red';
		}];
		yield 'string expression' => [static function (array &$i): void {
			$i['rules'][0]['expression'] = 'Male & Female';
		}];
		yield 'unknown rule field' => [static function (array &$i): void {
			$i['rules'][0]['other'] = true;
		}];
		yield 'unknown top-level field' => [static function (array &$i): void {
			$i['private'] = true;
		}];
	}

	public function testMissingFontSizesAreAllowed(): void {
		$input = self::validTemplate();
		unset($input['fontSizes']);

		$this->assertSame([], $this->validator->validate($input)['config']['fontSizes']);
	}

	public function testNullFontSizesAreAllowed(): void {
		$input = self::validTemplate();
		$input['fontSizes'] = null;

		$this->assertSame([], $this->validator->validate($input)['config']['fontSizes']);
	}

	#[DataProvider('invalidTemplates')]
	public function testRejectsInvalidData(callable $change): void {
		$input = self::validTemplate();
		$change($input);
		$this->expectException(\InvalidArgumentException::class);
		$this->validator->validate($input);
	}
}
