/**
 * Utility functions for handling array conversions
 */

/**
 * Convert AG Grid value to string array or null.
 * Handles various input types (null, undefined, empty string, array, comma-separated string).
 *
 * @param value - The value to convert
 * @return Parsed array of strings or null if empty/invalid
 */
export function parseArrayValue(value: unknown): string[] | null {
	// Handle null, undefined, or empty string as null
	if (value === null || value === undefined || value === '') {
		return null
	}

	// If already an array, filter out empty strings
	if (Array.isArray(value)) {
		const filtered = value.map(v => String(v).trim()).filter(Boolean)
		return filtered.length > 0 ? filtered : null
	}

	// If string, split by comma and filter
	if (typeof value === 'string') {
		const parts = value.split(',').map(s => s.trim()).filter(Boolean)
		return parts.length > 0 ? parts : null
	}

	return null
}
