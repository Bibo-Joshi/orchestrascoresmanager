import type { jsPDF } from 'jspdf'
import type { Styles } from 'jspdf-autotable'
import type { prepareEntries } from './data.ts'
import type { Setlist } from '@/api/generated/openapi/data-contracts'
import type { PdfTemplateFontSizes, PdfTemplateRule } from '@/utils/pdfTemplatesApi'

import { autoTable } from 'jspdf-autotable'
import { createRuleMatcher } from './rules.ts'

/**
 * Render the entries in the selected column order and apply the first matching rule per row.
 * @param doc The PDF document.
 * @param startY Vertical position of the table.
 * @param margin Page margin in mm.
 * @param entries Prepared headers, cells and rule facts.
 * @param setlist Setlist properties exposed to rules.
 * @param fontSizes Font sizes in points.
 * @param cellPadding Template padding in mm, omitted for the built-in template.
 * @param rules Shared template rules in priority order.
 */
export async function renderEntriesTable(
	doc: jsPDF,
	startY: number,
	margin: number,
	entries: ReturnType<typeof prepareEntries>,
	setlist: Setlist,
	fontSizes: Required<PdfTemplateFontSizes>,
	cellPadding: number | undefined,
	rules: PdfTemplateRule[],
): Promise<void> {
	const { columns, rows } = entries
	const headers = columns.map(({ label }) => label)
	const customTemplate = cellPadding !== undefined
	const columnStyles: Record<number, Partial<Styles>> = {}
	doc.setFontSize(customTemplate ? fontSizes.columnTitle : fontSizes.scoreRow)
	headers.forEach((header, index) => {
		columnStyles[index] = { minCellWidth: doc.getTextWidth(header) + 2 * (cellPadding ?? 1.5) }
	})

	const rowRules: Array<PdfTemplateRule | undefined> = []
	if (rules.length) {
		const matchRule = createRuleMatcher(rules)
		const setlistFacts = { ...setlist, tags: [...new Set(rows.flatMap(({ facts }) => facts.tags))] }
		for (const row of rows) {
			rowRules.push(await matchRule({ setlist: setlistFacts, setlistentry: row.facts }))
		}
	}

	autoTable(doc, {
		startY,
		head: [headers],
		body: rows.map(({ cells }) => cells),
		theme: 'plain',
		margin: { left: margin, right: margin },
		tableWidth: doc.internal.pageSize.getWidth() - 2 * margin,
		styles: {
			fontSize: fontSizes.scoreRow,
			overflow: 'linebreak',
			lineWidth: 0,
			...(customTemplate ? { cellPadding } : {}),
		},
		headStyles: {
			...(customTemplate ? { fontSize: fontSizes.columnTitle } : {}),
			fontStyle: 'bold',
			fillColor: false,
			textColor: [0, 0, 0],
			lineColor: [0, 0, 0],
			lineWidth: { bottom: 0.8, top: 0, left: 0, right: 0 },
		},
		alternateRowStyles: customTemplate ? undefined : { fillColor: [245, 245, 245] },
		columnStyles,
		didParseCell: ({ section, row, cell }) => {
			if (!customTemplate || section !== 'body') {
				return
			}
			if (rows[row.index]?.isBreak) {
				cell.styles.fontSize = fontSizes.breakRow
				cell.styles.fontStyle = 'bold'
			}
			const rule = rowRules[row.index]
			if (rule?.backgroundColor) {
				cell.styles.fillColor = rule.backgroundColor
				const red = parseInt(rule.backgroundColor.slice(1, 3), 16)
				const green = parseInt(rule.backgroundColor.slice(3, 5), 16)
				const blue = parseInt(rule.backgroundColor.slice(5, 7), 16)
				const luminance = red * 0.299 + green * 0.587 + blue * 0.114
				cell.styles.textColor = luminance < 128 ? 255 : 0
			}
			if (typeof rule?.cellPadding === 'number') {
				cell.styles.cellPadding = rule.cellPadding
			}
		},
	})
}
