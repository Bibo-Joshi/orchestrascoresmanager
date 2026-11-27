<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Controller;

use OCP\AppFramework\OCS\OCSBadRequestException;

/**
 * Type checks for clef and transposition request parameters.
 * Semantic validation is done by the services.
 */
trait NotationInputValidationTrait {
	/**
	 * @param array<array-key, mixed>|null $transposition
	 * @throws OCSBadRequestException if a value is not an integer
	 */
	private function assertTranspositionTypes(?array $transposition): void {
		foreach ($transposition ?? [] as $value) {
			if (!is_int($value)) {
				throw new OCSBadRequestException('The transposition must only contain integer values');
			}
		}
	}

	/**
	 * @param array<array-key, mixed>|null $clef
	 * @throws OCSBadRequestException if a value has the wrong type
	 */
	private function assertClefTypes(?array $clef): void {
		if ($clef === null) {
			return;
		}
		if (
			(isset($clef['id']) && !is_int($clef['id']))
			|| (isset($clef['sign']) && !is_string($clef['sign']))
			|| (isset($clef['line']) && !is_int($clef['line']))
			|| (isset($clef['octave']) && !is_int($clef['octave']))
			|| (isset($clef['title']) && !is_string($clef['title']))
		) {
			throw new OCSBadRequestException('The clef contains values of the wrong type');
		}
	}
}
