import { showError } from '@nextcloud/dialogs'

/**
 * Extract user-friendly error message from various error types.
 * Prioritizes OCS error messages from NextCloud API responses.
 *
 * @param error - The error object (axios error, Error, or unknown)
 * @return User-friendly error message string
 */
export function extractErrorMessage(error: unknown): string {
	// Check if it's an axios error with OCS response
	if (typeof error === 'object' && error !== null) {
		const axiosError = error as {
			response?: {
				data?: {
					ocs?: {
						meta?: {
							message?: string
						}
					}
				}
			}
			message?: string
		}

		// Try to get OCS message (most user-friendly)
		const ocsMessage = axiosError.response?.data?.ocs?.meta?.message
		if (ocsMessage) {
			return ocsMessage
		}

		// Fallback to axios error message
		if (axiosError.message) {
			return axiosError.message
		}
	}

	// Fallback to Error.message if it's an Error instance
	if (error instanceof Error) {
		return error.message
	}

	// Last resort: convert to string
	return String(error)
}

/**
 * Try executing a callback and show an error notification if it fails.
 * Automatically extracts user-friendly error messages from OCS responses.
 *
 * @param callback - Async function to execute
 * @param errorPrefix - Optional prefix for error message (e.g., "Failed to delete item: ")
 * @param errorCallback - Optional callback to execute in catch clause (receives the error)
 * @return Promise that resolves to the callback result or void if it fails
 *
 * @example
 * // Basic usage
 * await tryShowError(
 *   async () => await store.deleteItem(id),
 *   'Failed to delete item: '
 * )
 *
 * @example
 * // With error callback
 * await tryShowError(
 *   async () => await store.updateItem(id, data),
 *   'Failed to update item: ',
 *   (error) => console.error('Update failed:', error)
 * )
 *
 * @example
 * // With return value
 * const result = await tryShowError(
 *   async () => await store.createItem(data),
 *   'Failed to create item: '
 * )
 * if (result !== undefined) {
 *   // Use result
 * }
 */
export async function tryShowError<T>(
	callback: () => Promise<T>,
	errorPrefix = '',
	errorCallback?: (error: unknown) => void,
): Promise<T | void> {
	try {
		return await callback()
	} catch (error) {
		const message = extractErrorMessage(error)
		showError(errorPrefix + message)

		if (errorCallback) {
			errorCallback(error)
		}
	}
}
