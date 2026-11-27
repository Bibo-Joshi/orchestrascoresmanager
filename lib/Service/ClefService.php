<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Service;

use InvalidArgumentException;
use OCA\OrchestraScoresManager\Db\Clef;
use OCA\OrchestraScoresManager\Db\ClefMapper;
use OCA\OrchestraScoresManager\Db\Enum\ClefSign;
use OCA\OrchestraScoresManager\Policy\ClefPolicy;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;

class ClefService {
	private const MAX_TITLE_LENGTH = 160;
	private const MIN_LINE = 1;
	private const MAX_LINE = 5;
	private const MAX_OCTAVE_SHIFT = 2;

	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private readonly ClefMapper $clefMapper,
		private readonly AuthorizationService $authorizationService,
		private readonly ClefPolicy $clefPolicy,
		private readonly IL10N $l,
	) {
	}

	/**
	 * Get all clefs.
	 *
	 * @return array<Clef>
	 * @throws Exception
	 */
	public function getAllClefs(): array {
		$this->authorizationService->authorizePolicy($this->clefPolicy, PolicyInterface::ACTION_READ);
		return $this->clefMapper->findAll();
	}

	/**
	 * Find a clef entity by its ID.
	 *
	 * @param int $id
	 * @return Clef
	 * @throws InvalidArgumentException if the clef does not exist
	 * @throws Exception
	 */
	public function findClefEntity(int $id): Clef {
		$this->authorizationService->authorizePolicy($this->clefPolicy, PolicyInterface::ACTION_READ);
		try {
			return $this->clefMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Clef not found'));
		}
	}

	/**
	 * Get a serialized clef by its ID.
	 *
	 * @param int $id
	 * @return array
	 * @throws InvalidArgumentException if the clef does not exist
	 * @throws Exception
	 */
	public function getClefById(int $id): array {
		return $this->findClefEntity($id)->jsonSerialize();
	}

	/**
	 * Return the clef with exactly the given properties, creating it if no identical clef exists.
	 * Clefs that only differ by their title are distinct clefs.
	 *
	 * @param string $sign One of the values of ClefSign
	 * @param int|null $line Staff line 1..5, required for G, C and F clefs, must be null otherwise
	 * @param int $octave Octave shift
	 * @param string|null $title Optional title
	 * @return Clef
	 * @throws InvalidArgumentException if the parameters are invalid
	 * @throws Exception
	 */
	public function findOrCreateClef(string $sign, ?int $line, int $octave = 0, ?string $title = null): Clef {
		$this->authorizationService->authorizePolicy($this->clefPolicy, PolicyInterface::ACTION_CREATE);

		$sign = trim($sign);
		$title = $this->normalize($title);
		$this->validate($sign, $line, $octave, $title);

		$existing = $this->clefMapper->findByNotation($sign, $line, $octave, $title);
		if ($existing !== null) {
			return $existing;
		}

		return $this->clefMapper->insert(Clef::fromParams([
			'sign' => $sign,
			'line' => $line,
			'octave' => $octave,
			'title' => $title,
		]));
	}

	/**
	 * Update a clef. Parameters that are null are not changed. An empty title removes the title.
	 * Changing the sign to one without a line removes the line, unless a line is given.
	 *
	 * @param Clef $clef
	 * @param string|null $sign
	 * @param int|null $line
	 * @param int|null $octave
	 * @param string|null $title
	 * @return array The complete serialized clef
	 * @throws InvalidArgumentException if the parameters are invalid or an identical clef exists
	 * @throws Exception
	 */
	public function updateClef(Clef $clef, ?string $sign = null, ?int $line = null, ?int $octave = null, ?string $title = null): array {
		$this->authorizationService->authorizePolicy($this->clefPolicy, PolicyInterface::ACTION_UPDATE, $clef);

		$newSign = $sign !== null ? trim($sign) : $clef->getSign();
		$newLine = $line ?? $clef->getLine();
		if ($line === null && ClefSign::tryFrom($newSign)?->hasLine() === false) {
			$newLine = null;
		}
		$newOctave = $octave ?? $clef->getOctave();
		$newTitle = $title !== null ? $this->normalize($title) : $clef->getTitle();
		$this->validate($newSign, $newLine, $newOctave, $newTitle);

		$duplicate = $this->clefMapper->findByNotation($newSign, $newLine, $newOctave, $newTitle);
		if ($duplicate !== null && $duplicate->getId() !== $clef->getId()) {
			throw new InvalidArgumentException($this->l->t('An identical clef already exists'));
		}

		$clef->setSign($newSign);
		$clef->setLine($newLine);
		$clef->setOctave($newOctave);
		$clef->setTitle($newTitle);
		return $this->clefMapper->update($clef)->jsonSerialize();
	}

	/**
	 * Delete a clef that is not used by any part variant or notation preset.
	 *
	 * @param int $id
	 * @throws InvalidArgumentException if the clef does not exist or is still in use
	 * @throws Exception
	 */
	public function deleteClef(int $id): void {
		$this->authorizationService->authorizePolicy($this->clefPolicy, PolicyInterface::ACTION_DELETE);
		try {
			$clef = $this->clefMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new InvalidArgumentException($this->l->t('Clef not found'));
		}
		// The database restricts this as well, but this gives a friendly error
		if ($this->clefMapper->isInUse($id)) {
			throw new InvalidArgumentException($this->l->t('Clef is still in use'));
		}
		$this->clefMapper->delete($clef);
	}

	/**
	 * Trim a value and convert empty strings to null.
	 */
	private function normalize(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}

	/**
	 * Validate the properties of a clef.
	 *
	 * @throws InvalidArgumentException
	 */
	private function validate(string $sign, ?int $line, int $octave, ?string $title): void {
		$clefSign = ClefSign::tryFrom($sign);
		if ($clefSign === null) {
			throw new InvalidArgumentException($this->l->t('Unsupported clef sign "%s"', [$sign]));
		}
		if ($clefSign->hasLine()) {
			if ($line === null || $line < self::MIN_LINE || $line > self::MAX_LINE) {
				throw new InvalidArgumentException($this->l->t('The line of a %s clef must be between %d and %d', [$sign, self::MIN_LINE, self::MAX_LINE]));
			}
		} elseif ($line !== null) {
			throw new InvalidArgumentException($this->l->t('A %s clef must not have a line', [$sign]));
		}
		if (abs($octave) > self::MAX_OCTAVE_SHIFT) {
			throw new InvalidArgumentException($this->l->t('The octave of a clef must be between %d and %d', [-self::MAX_OCTAVE_SHIFT, self::MAX_OCTAVE_SHIFT]));
		}
		if ($title !== null && mb_strlen($title) > self::MAX_TITLE_LENGTH) {
			throw new InvalidArgumentException($this->l->t('The title must not be longer than %d characters', [self::MAX_TITLE_LENGTH]));
		}
	}
}
