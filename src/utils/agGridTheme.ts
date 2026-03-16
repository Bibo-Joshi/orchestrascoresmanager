/**
 * AG Grid theme configuration for Nextcloud integration.
 *
 * This module provides a customized AG Grid theme that matches Nextcloud's design
 * system by using Nextcloud CSS variables. It supports both light and dark modes.
 *
 * Usage:
 * ```typescript
 * import { nextcloudTheme } from '@/utils/agGridTheme'
 * // Use in AgGridVue component: :theme="nextcloudTheme"
 * ```
 */
import { themeQuartz } from 'ag-grid-community'
import type { Theme } from 'ag-grid-community'

/**
 * Shared theme parameters using Nextcloud CSS variables.
 * These CSS variables automatically adjust for light/dark mode.
 */
const nextcloudThemeParams = {
	// Core colors - Nextcloud CSS variables auto-adapt to light/dark mode
	backgroundColor: 'var(--color-main-background)',
	foregroundColor: 'var(--color-main-text)',
	accentColor: 'var(--color-primary-element)',
	borderColor: 'var(--color-border)',

	// Header styling - use normal background color
	headerBackgroundColor: 'var(--color-main-background)',
	headerTextColor: 'var(--color-main-text)',

	// Row styling - uniform background with border lines between rows
	rowHoverColor: 'var(--color-background-hover)',
	selectedRowBackgroundColor: 'var(--color-primary-element-light)',
	rowBorder: { color: 'var(--color-border)', width: 1, style: 'solid' },

	// Pinned column styling - no vertical border between pinned and regular columns
	pinnedColumnBorder: false,

	// Chrome (toolbars, panels) styling
	chromeBackgroundColor: 'var(--color-main-background)',

	// Border settings - no wrapper border to blend with Nextcloud UI
	wrapperBorder: false,
	wrapperBorderRadius: 0,

	// Text colors
	textColor: 'var(--color-main-text)',
	subtleTextColor: 'var(--color-text-maxcontrast)',

	// Input/form elements
	invalidColor: 'var(--color-error)',

	// Selection colors
	rangeSelectionBackgroundColor: 'var(--color-primary-element-extra-light)',
	rangeSelectionBorderColor: 'var(--color-primary-element)',

	// Icon styling
	iconColor: 'var(--color-main-text)',

	// Cell editing
	cellTextColor: 'var(--color-main-text)',

	// Tooltips
	tooltipBackgroundColor: 'var(--color-main-background)',
	tooltipTextColor: 'var(--color-main-text)',
	tooltipBorder: { color: 'var(--color-border)' },

	// Menu styling
	menuBackgroundColor: 'var(--color-main-background)',
	menuTextColor: 'var(--color-main-text)',
	menuBorder: { color: 'var(--color-border)' },
	menuSeparatorColor: 'var(--color-border)',

	// Panel styling
	panelBackgroundColor: 'var(--color-main-background)',

	// Column drop styling
	columnDropCellBackgroundColor: 'var(--color-primary-element-light)',

	// Focus styling
	focusShadow: { color: 'var(--color-primary-element)' },
} as const

/**
 * Creates a Nextcloud-themed AG Grid theme with both light and dark mode support.
 *
 * The theme uses CSS variables from Nextcloud for seamless integration with the
 * application's color scheme. The `data-ag-theme-mode` attribute on a parent
 * element controls which mode is active ('light' or 'dark').
 *
 * @return AG Grid Theme configured with Nextcloud styling
 */
export function createNextcloudTheme(): Theme {
	return themeQuartz
		.withParams({ ...nextcloudThemeParams, browserColorScheme: 'light' }, 'light')
		.withParams({ ...nextcloudThemeParams, browserColorScheme: 'dark' }, 'dark')
}

/**
 * Pre-configured Nextcloud-themed AG Grid theme.
 *
 * This theme integrates with Nextcloud's CSS variables and supports both
 * light and dark modes. Set `data-ag-theme-mode="light"` or `data-ag-theme-mode="dark"`
 * on a parent element to switch between modes.
 */
export const nextcloudTheme: Theme = createNextcloudTheme()
