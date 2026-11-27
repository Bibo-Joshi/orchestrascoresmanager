<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCP\IL10N;

/**
 * Validates and normalizes transpositions given as {diatonic, chromatic, octave}.
 */
class TranspositionValidator {
	private const MAX_DIATONIC = 28;
	private const MAX_CHROMATIC = 48;
	private const MAX_OCTAVE = 4;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly IL10N $l,
	) {
	}

	/**
	 * Validate a transposition and return it with integer values.
	 *
	 * @param array<array-key, mixed> $transposition Must contain the integer keys diatonic, chromatic and octave
	 * @return array{diatonic: int, chromatic: int, octave: int}
	 * @throws InvalidArgumentException if a value is missing, not an integer or out of range
	 */
	public function normalize(array $transposition): array {
		$limits = [
			'diatonic' => self::MAX_DIATONIC,
			'chromatic' => self::MAX_CHROMATIC,
			'octave' => self::MAX_OCTAVE,
		];
		$result = [];
		foreach ($limits as $key => $limit) {
			$value = $transposition[$key] ?? null;
			if (!is_int($value)) {
				throw new InvalidArgumentException($this->l->t('The transposition requires the integer values diatonic, chromatic and octave'));
			}
			if (abs($value) > $limit) {
				throw new InvalidArgumentException($this->l->t('The transposition value "%s" must be between %d and %d', [$key, -$limit, $limit]));
			}
			$result[$key] = $value;
		}
		return $result;
	}
}
