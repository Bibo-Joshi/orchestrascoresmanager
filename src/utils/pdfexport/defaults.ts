/**
 * Define the built-in PDF template configuration.
 */

import type { SetlistTemplate } from '@/api/generated/openapi/data-contracts'

type PdfTemplateFontSizes = NonNullable<SetlistTemplate['fontSizes']>

export const FULL_PDF_HEADER_INFO: SetlistTemplate['headerInfo'] = {
	entries: ['date', 'startTime', 'endTime', 'playDuration', 'breaksDuration', 'overallDuration', 'folderCollection'],
	layout: 'table',
}

export const COMPACT_PDF_HEADER_INFO: SetlistTemplate['headerInfo'] = {
	entries: ['date', 'startTime', 'overallDuration'],
	layout: 'subtitle',
}

export type PdfTemplateConfig = {
	headerInfo: SetlistTemplate['headerInfo']
	fontSizes: Required<PdfTemplateFontSizes>
	margin: number
	cellPadding: number
	rules: SetlistTemplate['rules']
}

export const DEFAULT_PDF_TEMPLATE: PdfTemplateConfig = {
	headerInfo: FULL_PDF_HEADER_INFO,
	fontSizes: {
		title: 16,
		subtitle: 11,
		colorLegend: 8,
		columnTitle: 8,
		scoreRow: 8,
		breakRow: 8,
	},
	margin: 8,
	cellPadding: 5 * 25.4 / 72,
	rules: [],
}
