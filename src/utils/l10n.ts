/**
 * Translation utility that wraps @nextcloud/l10n with the app name pre-filled
 */
import { t as nextcloudT, n as nextcloudN } from '@nextcloud/l10n'

const APP_NAME = 'orchestrascoresmanager'

/**
 * Global translation function with app name pre-filled
 * @param text The text to translate
 * @param vars Optional variables for placeholders
 * @return Translated string
 */
export function t(text: string, vars?: Record<string, unknown>): string {
	return nextcloudT(APP_NAME, text, vars)
}

/**
 * Global plural translation function with app name pre-filled
 * @param textSingular The singular form to translate
 * @param textPlural The plural form to translate
 * @param count The number to determine singular/plural
 * @param vars Optional variables for placeholders
 * @return Translated string in correct plural form
 */
export function n(textSingular: string, textPlural: string, count: number, vars?: Record<string, unknown>): string {
	return nextcloudN(APP_NAME, textSingular, textPlural, count, vars)
}
