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

	public static function invalidTemplates(): iterable {
		yield 'missing font sizes' => [static function (array &$i): void {
			unset($i['fontSizes']);
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
		yield 'too many rules' => [static function (array &$i): void {
			$i['rules'] = array_fill(0, 31, $i['rules'][0]);
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

	#[DataProvider('invalidTemplates')]
	public function testRejectsInvalidData(callable $change): void {
		$input = self::validTemplate();
		$change($input);
		$this->expectException(\InvalidArgumentException::class);
		$this->validator->validate($input);
	}
}
