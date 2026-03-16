/**
 * AG Grid localization utility for Nextcloud integration.
 *
 * This module provides localization for AG Grid texts based on the user's
 * Nextcloud locale setting. It maps Nextcloud language codes to AG Grid's
 * built-in locale texts.
 */
import { getLanguage } from '@nextcloud/l10n'
import {
	AG_GRID_LOCALE_EN,
	AG_GRID_LOCALE_DE,
	AG_GRID_LOCALE_FR,
	AG_GRID_LOCALE_ES,
	AG_GRID_LOCALE_IT,
	AG_GRID_LOCALE_NL,
	AG_GRID_LOCALE_PT,
	AG_GRID_LOCALE_BR,
	AG_GRID_LOCALE_PL,
	AG_GRID_LOCALE_CZ,
	AG_GRID_LOCALE_SK,
	AG_GRID_LOCALE_HU,
	AG_GRID_LOCALE_RO,
	AG_GRID_LOCALE_BG,
	AG_GRID_LOCALE_HR,
	AG_GRID_LOCALE_UA,
	AG_GRID_LOCALE_TR,
	AG_GRID_LOCALE_GR,
	AG_GRID_LOCALE_FI,
	AG_GRID_LOCALE_SE,
	AG_GRID_LOCALE_DK,
	AG_GRID_LOCALE_NO,
	AG_GRID_LOCALE_JP,
	AG_GRID_LOCALE_KR,
	AG_GRID_LOCALE_CN,
	AG_GRID_LOCALE_TW,
	AG_GRID_LOCALE_HK,
	AG_GRID_LOCALE_IL,
	AG_GRID_LOCALE_EG,
	AG_GRID_LOCALE_IR,
	AG_GRID_LOCALE_VN,
	AG_GRID_LOCALE_PK,
} from '@ag-grid-community/locale'

/**
 * Mapping from Nextcloud language codes to AG Grid locale texts.
 * Nextcloud uses BCP 47 language tags (e.g., 'de', 'en', 'fr').
 */
const localeMap: Record<string, Record<string, string>> = {
	en: AG_GRID_LOCALE_EN,
	de: AG_GRID_LOCALE_DE,
	fr: AG_GRID_LOCALE_FR,
	es: AG_GRID_LOCALE_ES,
	it: AG_GRID_LOCALE_IT,
	nl: AG_GRID_LOCALE_NL,
	pt: AG_GRID_LOCALE_PT,
	'pt-br': AG_GRID_LOCALE_BR,
	pl: AG_GRID_LOCALE_PL,
	cs: AG_GRID_LOCALE_CZ,
	sk: AG_GRID_LOCALE_SK,
	hu: AG_GRID_LOCALE_HU,
	ro: AG_GRID_LOCALE_RO,
	bg: AG_GRID_LOCALE_BG,
	hr: AG_GRID_LOCALE_HR,
	uk: AG_GRID_LOCALE_UA,
	tr: AG_GRID_LOCALE_TR,
	el: AG_GRID_LOCALE_GR,
	fi: AG_GRID_LOCALE_FI,
	sv: AG_GRID_LOCALE_SE,
	da: AG_GRID_LOCALE_DK,
	nb: AG_GRID_LOCALE_NO,
	nn: AG_GRID_LOCALE_NO,
	no: AG_GRID_LOCALE_NO,
	ja: AG_GRID_LOCALE_JP,
	ko: AG_GRID_LOCALE_KR,
	zh: AG_GRID_LOCALE_CN,
	'zh-cn': AG_GRID_LOCALE_CN,
	'zh-tw': AG_GRID_LOCALE_TW,
	'zh-hk': AG_GRID_LOCALE_HK,
	he: AG_GRID_LOCALE_IL,
	ar: AG_GRID_LOCALE_EG,
	fa: AG_GRID_LOCALE_IR,
	vi: AG_GRID_LOCALE_VN,
	ur: AG_GRID_LOCALE_PK,
}

/**
 * Gets the AG Grid locale texts based on the current Nextcloud language.
 *
 * @return AG Grid locale text object for the current language, or English as fallback
 */
export function getAgGridLocaleText(): Record<string, string> {
	const ncLanguage = getLanguage().toLowerCase()

	// Try exact match first
	if (localeMap[ncLanguage]) {
		return localeMap[ncLanguage]
	}

	// Try base language (e.g., 'de' from 'de-DE')
	const baseLanguage = ncLanguage.split('-')[0]
	if (localeMap[baseLanguage]) {
		return localeMap[baseLanguage]
	}

	// Fall back to English
	return AG_GRID_LOCALE_EN
}
