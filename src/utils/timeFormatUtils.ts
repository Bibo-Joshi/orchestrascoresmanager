import { t } from './l10n'

/**
 * Format seconds as (HH:)MM:SS
 *
 * @param seconds - The duration in seconds
 * @return The formatted duration string
 */
export function formatDurationHHMMSS(seconds: number): string {
	const hrs = Math.floor(seconds / 3600)
	const mins = Math.floor((seconds % 3600) / 60)
	const secs = seconds % 60
	if (hrs > 0) {
		return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
	} else {
		return `${mins}:${secs.toString().padStart(2, '0')}`
	}
}

/**
 * Parse (HH:)MM:SS string into total seconds
 *
 * @param durationStr - The duration string to parse
 * @return The total duration in seconds or null if empty
 * @throws Error if the format is invalid
 */
export function parseDurationHHMMSS(durationStr: string | undefined | null): number | null {
	if (!durationStr || durationStr.trim() === '') {
		return null
	}
	const parts = durationStr.split(':').map(part => parseInt(part, 10))
	if (parts.some(isNaN)) {
		throw new Error(t('Invalid duration format'))
	}

	let totalSeconds = 0
	if (parts.length === 3) {
		// HH:MM:SS
		totalSeconds += parts[0] * 3600 // hours
		totalSeconds += parts[1] * 60 // minutes
		totalSeconds += parts[2] // seconds
	} else if (parts.length === 2) {
		// MM:SS
		totalSeconds += parts[0] * 60 // minutes
		totalSeconds += parts[1] // seconds
	} else if (parts.length === 1) {
		// SS
		totalSeconds += parts[0] // seconds
	} else {
		throw new Error(t('Invalid duration format'))
	}

	return totalSeconds
}

/**
 * Format seconds as HH:MM (without seconds).
 * Rounds to the nearest minute (rounds up if remaining seconds are >= 30).
 * Hours are always shown with a leading zero for clarity.
 *
 * @param seconds - The duration in seconds
 * @return The formatted duration string
 */
export function formatDurationHHMM(seconds: number): string {
	const totalMinutes = Math.round(seconds / 60)
	const hrs = Math.floor(totalMinutes / 60)
	const mins = totalMinutes % 60
	return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`
}

/**
 * Parse (HH:)MM string into total seconds
 *
 * @param durationStr - The duration string to parse
 * @return The total duration in seconds or null if empty
 * @throws Error if the format is invalid
 */
export function parseDurationHHMM(durationStr: string | undefined | null): number | null {
	if (!durationStr || durationStr.trim() === '') {
		return null
	}
	const parts = durationStr.split(':').map(part => parseInt(part, 10))
	if (parts.some(isNaN)) {
		throw new Error(t('Invalid duration format'))
	}

	let totalSeconds = 0
	if (parts.length === 2) {
		// HH:MM
		totalSeconds += parts[0] * 3600 // hours
		totalSeconds += parts[1] * 60 // minutes
	} else if (parts.length === 1) {
		// MM
		totalSeconds += parts[0] * 60 // minutes
	} else {
		throw new Error(t('Invalid duration format'))
	}

	return totalSeconds
}

/**
 * Restrict input to only digits and colons
 *
 * @param event - The input or paste event
 */
export function restrictToTimeFormat(event: Event): void {
	const input = event.target as HTMLInputElement
	if (event.type === 'paste') {
		event.preventDefault()
		const pasteEvent = event as ClipboardEvent
		const pastedText = pasteEvent.clipboardData?.getData('text') || ''
		const filtered = pastedText.replace(/[^0-9:]/g, '')
		const start = input.selectionStart || 0
		const end = input.selectionEnd || 0
		const currentValue = input.value
		input.value = currentValue.substring(0, start) + filtered + currentValue.substring(end)
		const newPosition = start + filtered.length
		input.setSelectionRange(newPosition, newPosition)
		// Trigger input event to update v-model
		input.dispatchEvent(new Event('input', { bubbles: true }))
	} else if (event.type === 'input') {
		const oldValue = input.value
		const filteredValue = oldValue.replace(/[^0-9:]/g, '')
		if (filteredValue !== oldValue) {
			const oldStart = input.selectionStart || 0
			const removedChars = oldValue.length - filteredValue.length
			input.value = filteredValue
			// Position cursor accounting for removed characters
			const newPosition = Math.max(0, oldStart - removedChars)
			input.setSelectionRange(newPosition, newPosition)
		}
	}
}
