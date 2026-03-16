import { toValue } from 'vue'
import { useFormatTime } from '@nextcloud/vue/composables/useFormatDateTime'
import { t } from '@/utils/l10n'
import type { FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

/**
 * Format a date string (Y-m-d format) for display using NextCloud's useFormatTime
 *
 * @param dateStr The date string in Y-m-d format
 * @return Formatted date string in medium format
 */
export function formatDateStr(dateStr: string): string {
	// Parse date parts to avoid timezone issues - we treat dates as local dates
	const [year, month, day] = dateStr.split('-').map(Number)
	const date = new Date(year, month - 1, day)
	return toValue(useFormatTime(date, { format: { dateStyle: 'medium' } }))
}

/**
 * Format an iso datetime string for display using NextCloud's useFormatTime
 *
 * @param dateTimeStr The datetime string
 * @return Formatted datetime string with date and time (hour:minute)
 */
export function formatDateTimeStr(dateTimeStr: string): string {
	const date = new Date(dateTimeStr)
	return toValue(useFormatTime(date, { format: { dateStyle: 'medium', timeStyle: 'short' } }))
}

/**
 * Format version date range for display
 *
 * @param version - The version to format
 * @return Formatted date range string
 */
export function formatVersionDateRange(version: FolderCollectionVersion): string {
	if (version.validTo === null) {
		return `${formatDateStr(version.validFrom)} - ${t('Present')}`
	}
	return `${formatDateStr(version.validFrom)} - ${formatDateStr(version.validTo)}`
}
