<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

/**
 * Validate public template metadata and formatting properties.
 * Rule expressions are intentionally opaque: their syntax belongs to the
 * TypeScript rules engine selected for PDF rendering.
 */
class SetlistTemplateValidator {
	private const FONT_KEYS = ['title', 'subtitle', 'colorLegend', 'columnTitle', 'scoreRow', 'breakRow'];
	private const CONFIG_KEYS = ['fontSizes', 'margin', 'cellPadding', 'rules'];

	/**
	 * @return array{name:string,description:?string,config:array}
	 */
	public function validate(array $input): array {
		$this->assertKeys($input, ['id', 'name', 'description', ...self::CONFIG_KEYS], 'template');

		$name = $input['name'] ?? null;
		if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 160) {
			throw new \InvalidArgumentException('Invalid template name');
		}
		$description = $input['description'] ?? null;
		if ($description !== null && (!is_string($description) || mb_strlen($description) > 2000)) {
			throw new \InvalidArgumentException('Invalid template description');
		}

		/** @var mixed $fontSizes */
		$fontSizes = $input['fontSizes'] ?? null;
		if (!is_array($fontSizes) || (array_is_list($fontSizes) && $fontSizes !== [])) {
			throw new \InvalidArgumentException('fontSizes must be an object');
		}
		/** @var array<string, mixed> $fontSizes */
		$this->assertKeys($fontSizes, self::FONT_KEYS, 'fontSizes');
		foreach (array_keys($fontSizes) as $key) {
			$this->number($fontSizes[$key], "fontSizes.$key", 5, 64);
		}

		$margin = $this->number($input['margin'] ?? null, 'margin', 0, 50);
		$cellPadding = $this->number($input['cellPadding'] ?? null, 'cellPadding', 0, 20);

		$rules = $input['rules'] ?? null;
		if (!is_array($rules) || !array_is_list($rules) || count($rules) > 30) {
			throw new \InvalidArgumentException('Invalid formatting rules');
		}
		$seenIds = [];
		$seenOrders = [];
		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				throw new \InvalidArgumentException('A formatting rule must be an object');
			}
			$this->assertKeys($rule, ['id', 'order', 'label', 'expression', 'backgroundColor', 'cellPadding'], 'rule');
			$id = $rule['id'] ?? null;
			$order = $rule['order'] ?? null;
			if (!is_string($id) || trim($id) === '' || strlen($id) > 64 || isset($seenIds[$id])
				|| !is_int($order) || $order < 0 || isset($seenOrders[$order])) {
				throw new \InvalidArgumentException('Invalid or duplicate rule ID/order');
			}
			$seenIds[$id] = true;
			$seenOrders[$order] = true;
			if (array_key_exists('label', $rule)
				&& $rule['label'] !== null
				&& (!is_string($rule['label']) || mb_strlen($rule['label']) > 160)) {
				throw new \InvalidArgumentException('Invalid rule label');
			}
			// Deliberately do not validate the expression's rule-engine schema.
			if (!isset($rule['expression']) || !is_array($rule['expression'])) {
				throw new \InvalidArgumentException('Rule expression must be a JSON object');
			}
			if (array_key_exists('backgroundColor', $rule) && $rule['backgroundColor'] !== null
				&& (!is_string($rule['backgroundColor']) || preg_match('/^#[0-9a-fA-F]{6}$/', $rule['backgroundColor']) !== 1)) {
				throw new \InvalidArgumentException('Invalid rule background color');
			}
			if (array_key_exists('cellPadding', $rule) && $rule['cellPadding'] !== null) {
				$this->number($rule['cellPadding'], 'rule.cellPadding', 0, 20);
			}
		}

		$config = [
			'fontSizes' => $fontSizes,
			'margin' => $margin,
			'cellPadding' => $cellPadding,
			'rules' => $rules,
		];
		$json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		if (strlen($json) > 60000) {
			throw new \InvalidArgumentException('Template configuration is too large');
		}

		return [
			'name' => trim($name),
			'description' => $description,
			'config' => $config,
		];
	}

	private function number(mixed $value, string $field, float $min, float $max): float {
		if ((!is_float($value) && !is_int($value)) || !is_finite((float)$value) || $value < $min || $value > $max) {
			throw new \InvalidArgumentException("Invalid $field");
		}
		return (float)$value;
	}

	/** @param list<string> $allowed */
	private function assertKeys(array $input, array $allowed, string $name): void {
		foreach (array_keys($input) as $key) {
			if (!in_array($key, $allowed, true)) {
				throw new \InvalidArgumentException("Unknown $name field: $key");
			}
		}
	}
}
