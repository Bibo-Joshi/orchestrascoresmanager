import type { Table as AutoTable } from 'jspdf-autotable'
import type { PdfSetlistData } from './data.ts'
import type { PdfTemplate } from '@/utils/pdfTemplatesApi'

import { jsPDF } from 'jspdf'
import { autoTable } from 'jspdf-autotable'
import { computeSummary, formatDateTime, prepareEntries } from './data.ts'
import { DEFAULT_PDF_TEMPLATE_FONTS } from './defaults.ts'
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
export async function exportSetlistToPdf(data: PdfSetlistData, template?: PdfTemplate): Promise<void> {
	const { setlist, entries, getScoreById, folderCollection } = data
	const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
	const pageWidth = doc.internal.pageSize.getWidth()
	const margin = template?.margin ?? 8
	const contentWidth = pageWidth - 2 * margin
	const fontSizes = { ...DEFAULT_PDF_TEMPLATE_FONTS, ...template?.fontSizes }
	const rules = [...(template?.rules ?? [])].sort((a, b) => a.order - b.order)

	let y = margin + 5
	if (template) {
		// Compact header for shared templates.
		const draftSuffix = setlist.isDraft ? ` (${t('Draft')})` : ''
		const headerTitle = `${setlist.title}${draftSuffix}`
		doc.setFont('helvetica', 'bold')
		doc.setFontSize(fontSizes.title)
		const titleLines = doc.splitTextToSize(headerTitle, contentWidth) as string[]
		doc.text(titleLines, margin, y)
		y += titleLines.length * (fontSizes.title * 0.4 + 1.2) + 1

		const summary = computeSummary(setlist, entries, getScoreById)
		const headerDetails: string[] = []
		if (setlist.startDateTime) {
			const { date, time } = formatDateTime(setlist.startDateTime)
			headerDetails.push(`${date} · ${time}`)
		}
		headerDetails.push(`${t('Overall Duration')}: ${formatDurationWithUnit(summary.totalDurationSecs)}`)
		doc.setFont('helvetica', 'normal')
		doc.setFontSize(fontSizes.subtitle)
		const detailLines = doc.splitTextToSize(headerDetails.join('  |  '), contentWidth) as string[]
		doc.text(detailLines, margin, y)
		y += detailLines.length * (fontSizes.subtitle * 0.4 + 1.1) + 3

		let x = margin
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
	} else {
		// --- Title ---
		const draftSuffix = setlist.isDraft ? ` (${t('Draft')})` : ''
		const titleText = `${t('Setlist')}: ${setlist.title}${draftSuffix}`
		y = addText(doc, titleText, margin, y, fontSizes.title, true)
		y += 2

		// --- Description ---
		if (setlist.description) {
			doc.setFontSize(fontSizes.subtitle)
			doc.setFont('helvetica', 'normal')
			const lines = doc.splitTextToSize(setlist.description, contentWidth) as string[]
			doc.text(lines, margin, y)
			y += lines.length * (fontSizes.subtitle * 0.4 + 1) + 3
		}

		// --- Folder collection ---
		if (folderCollection) {
			y = addText(
				doc,
				`${t('Folder Collection')}: ${folderCollection.title}`,
				margin,
				y,
				fontSizes.subtitle,
			)
			y += 1
		}

		// --- Info (summary) table — vertical two-column layout ---
		const summary = computeSummary(setlist, entries, getScoreById)

		y = addText(doc, t('Info'), margin, y, fontSizes.subtitle, true)

		const infoRows: string[][] = []

		if (setlist.startDateTime) {
			const { date, time, startOfDaySecs } = formatDateTime(setlist.startDateTime)
			// endTimeSecs may exceed 86400 (24h) for events that run past midnight; displayed as elapsed HH:MM
			const endTimeSecs = startOfDaySecs + summary.totalDurationSecs
			infoRows.push([t('Date'), date])
			infoRows.push([t('Start Time'), time])
			infoRows.push([t('End Time'), formatDurationHHMM(endTimeSecs)])
		}

		infoRows.push([t('Playtime'), formatDurationWithUnit(summary.playTimeSecs)])
		infoRows.push([t('Moderation & Breaks'), formatDurationWithUnit(summary.moderationAndBreakTimeSecs)])
		infoRows.push([t('Overall Duration'), formatDurationWithUnit(summary.totalDurationSecs)])

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
		if (infoTable !== false && infoTable.finalY) {
			y = infoTable.finalY + 4
		} else {
			y = y + 20
		}
	}

	await renderEntriesTable(doc, y, margin, prepareEntries(data), setlist, fontSizes, template?.cellPadding, rules)

	// Download the PDF
	const sanitizedTitle = setlist.title.replace(/[<>:"/\\|?*]/g, '_')
	doc.save(`${sanitizedTitle}_Setlist.pdf`)
}
