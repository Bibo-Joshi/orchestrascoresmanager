/**
 * Compose and download the complete setlist PDF document.
 */

import type { Table as AutoTable } from 'jspdf-autotable'
import type { PdfSetlistData } from './data.ts'
import type { SetlistTemplate } from '@/api/generated/openapi/data-contracts'

import { jsPDF } from 'jspdf'
import { autoTable } from 'jspdf-autotable'
import { computeSummary, formatDateTime, prepareEntries } from './data.ts'
import { DEFAULT_PDF_TEMPLATE } from './defaults.ts'
import { renderEntriesTable } from './table.ts'
import { t } from '@/utils/l10n'
import { formatDurationHHMM } from '@/utils/timeFormatUtils'

/**
 * Extension of jsPDF with the lastAutoTable property added by jspdf-autotable
 */
interface JsPDFWithAutoTable extends jsPDF {
	lastAutoTable: AutoTable | false
}

/**
 * Format seconds as HH:MM with a trailing "h" unit label, or empty string for null/undefined.
 * Used for duration values in the info table to make the unit explicit.
 *
 * @param seconds - Duration in seconds or null
 * @return Formatted duration string with unit suffix, or empty string
 */
function formatDurationWithUnit(seconds: number | null | undefined): string {
	if (seconds === null || seconds === undefined) {
		return ''
	}
	return `${formatDurationHHMM(seconds)} ${t('h')}`
}

/**
 * Add text to the document and advance the y position
 *
 * @param doc - The jsPDF document
 * @param text - The text to add
 * @param x - X position
 * @param y - Current Y position
 * @param fontSize - Font size
 * @param bold - Whether text should be bold
 * @return New Y position after adding text
 */
function addText(
	doc: jsPDF,
	text: string,
	x: number,
	y: number,
	fontSize: number,
	bold = false,
): number {
	doc.setFontSize(fontSize)
	doc.setFont('helvetica', bold ? 'bold' : 'normal')
	doc.text(text, x, y)
	return y + fontSize * 0.4 + 2
}

/**
 * Render and download a setlist PDF using the built-in or selected template.
 * @param data Setlist and score metadata.
 * @param template Shared formatting template, if selected.
 */
export async function exportSetlistToPdf(data: PdfSetlistData, template?: SetlistTemplate): Promise<void> {
	const { setlist, entries, getScoreById, folderCollection } = data
	const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
	const pageWidth = doc.internal.pageSize.getWidth()
	const headerInfo = template?.headerInfo ?? DEFAULT_PDF_TEMPLATE.headerInfo
	const margin = template?.margin ?? DEFAULT_PDF_TEMPLATE.margin
	const contentWidth = pageWidth - 2 * margin
	const fontSizes = { ...DEFAULT_PDF_TEMPLATE.fontSizes, ...template?.fontSizes }
	const rules = [...(template?.rules ?? DEFAULT_PDF_TEMPLATE.rules)].sort((a, b) => a.order - b.order)

	let y = margin + 5

	// --- Title ---
	const draftSuffix = setlist.isDraft ? ` (${t('Draft')})` : ''
	const titleText = headerInfo.layout === 'table'
		? `${t('Setlist')}: ${setlist.title}${draftSuffix}`
		: `${setlist.title}${draftSuffix}`

	doc.setFont('helvetica', 'bold')
	doc.setFontSize(fontSizes.title)
	const titleLines = doc.splitTextToSize(titleText, contentWidth) as string[]
	doc.text(titleLines, margin, y)
	y += titleLines.length * (fontSizes.title * 0.4 + 2) + 2

	// --- Description ---
	if (headerInfo.layout === 'table' && setlist.description) {
		doc.setFontSize(fontSizes.subtitle)
		doc.setFont('helvetica', 'normal')
		const lines = doc.splitTextToSize(setlist.description, contentWidth) as string[]
		doc.text(lines, margin, y)
		y += lines.length * (fontSizes.subtitle * 0.4 + 1) + 3
	}

	// --- Header information ---
	const summary = computeSummary(setlist, entries, getScoreById)
	const dateTime = setlist.startDateTime ? formatDateTime(setlist.startDateTime) : null

	type HeaderEntry = SetlistTemplate['headerInfo']['entries'][number]

	const info: Record<HeaderEntry, [string, string] | null> = {
		date: dateTime ? [t('Date'), dateTime.date] : null,
		startTime: dateTime ? [t('Start Time'), dateTime.time] : null,
		endTime: dateTime
			? [t('End Time'), formatDurationHHMM(dateTime.startOfDaySecs + summary.totalDurationSecs)]
			: null,
		playDuration: [t('Playtime'), formatDurationWithUnit(summary.playTimeSecs)],
		breaksDuration: [t('Moderation & Breaks'), formatDurationWithUnit(summary.moderationAndBreakTimeSecs)],
		overallDuration: [t('Overall Duration'), formatDurationWithUnit(summary.totalDurationSecs)],
		folderCollection: folderCollection ? [t('Folder Collection'), folderCollection.title] : null,
	}

	const infoRows = headerInfo.entries.flatMap((entry) => {
		const row = info[entry]
		return row ? [row] : []
	})

	if (infoRows.length > 0) {
		if (headerInfo.layout === 'table') {
			y = addText(doc, t('Info'), margin, y, fontSizes.subtitle, true)

			autoTable(doc, {
				startY: y,
				showHead: 'never',
				theme: 'plain',
				body: infoRows,
				margin: { left: margin, right: margin },
				tableWidth: 'wrap',
				styles: { fontSize: fontSizes.scoreRow, cellWidth: 'wrap' },
				columnStyles: {
					0: { fontStyle: 'bold', cellPadding: { left: 0, vertical: 1, right: 2 }, valign: 'middle' },
				},
			})

			const infoTable = (doc as JsPDFWithAutoTable).lastAutoTable
			y = infoTable !== false && infoTable.finalY ? infoTable.finalY + 4 : y + 20
		} else {
			const subtitle = infoRows.map(([label, value]) => `${label}: ${value}`).join('  |  ')

			doc.setFont('helvetica', 'normal')
			doc.setFontSize(fontSizes.subtitle)
			const lines = doc.splitTextToSize(subtitle, contentWidth) as string[]
			doc.text(lines, margin, y)
			y += lines.length * (fontSizes.subtitle * 0.4 + 1.1) + 3
		}
	}

	// --- Color legend ---
	if (rules.some((rule) => rule.label && rule.backgroundColor)) {
		let x = margin
		doc.setFont('helvetica', 'normal')
		doc.setFontSize(fontSizes.colorLegend)
		const lineHeight = Math.max(6, fontSizes.colorLegend * 0.4 + 2)
		const pageBottom = doc.internal.pageSize.getHeight() - margin
		for (const rule of rules) {
			if (!rule.label || !rule.backgroundColor) {
				continue
			}
			const label = doc.splitTextToSize(rule.label, contentWidth - 9) as string[]
			const itemWidth = Math.max(...label.map((line) => doc.getTextWidth(line))) + 9
			if (x + itemWidth > pageWidth - margin && x > margin) {
				x = margin
				y += lineHeight
			}
			if (y + (label.length - 1) * lineHeight > pageBottom) {
				doc.addPage()
				x = margin
				y = margin + lineHeight
			}
			doc.setFillColor(rule.backgroundColor)
			doc.rect(x, y - 2.5, 3.5, 3.5, 'F')
			doc.text(label, x + 5, y + 0.2)
			if (label.length > 1) {
				x = margin
				y += lineHeight * label.length
			} else {
				x += itemWidth
			}
		}
		y += lineHeight
	}

	await renderEntriesTable(doc, y, margin, prepareEntries(data), setlist, fontSizes, template?.cellPadding ?? DEFAULT_PDF_TEMPLATE.cellPadding, rules)

	// Download the PDF
	const sanitizedTitle = setlist.title.replace(/[<>:"/\\|?*]/g, '_')
	doc.save(`${sanitizedTitle}_Setlist.pdf`)
}
