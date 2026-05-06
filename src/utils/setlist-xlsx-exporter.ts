/**
 * XLSX exporter for setlist GEMA report.
 * Generates an XLSX file with the setlist entries for GEMA reporting.
 */
import { Workbook } from 'exceljs'
import { t } from '@/utils/l10n'
import type { Setlist, SetlistEntry, Score } from '@/api/generated/openapi/data-contracts'
import { isBreakEntry } from '@/utils/setlistScoreUtils'

/**
 * Input data for generating the GEMA report
 */
export interface GemaReportData {
	setlist: Setlist
	entries: SetlistEntry[]
	getScoreById: (id: number) => Score | undefined
}

/**
 * Export a setlist as a GEMA report XLSX file.
 * The file will be named after the setlist title.
 *
 * Columns:
 * 1. Index (position in the setlist)
 * 2. Title
 * 3. Publisher
 * 4. Year
 * 5. Composer
 * 6. Arranger
 * 7. GEMA IDs
 * 8. Medley Contents
 *
 * @param data - The setlist data for the report
 */
export async function exportSetlistToGemaXlsx(data: GemaReportData): Promise<void> {
	const { setlist, entries, getScoreById } = data

	const workbook = new Workbook()
	const worksheet = workbook.addWorksheet(setlist.title)

	// Configure column widths
	worksheet.columns = [
		{ width: 8 },
		{ width: 40 },
		{ width: 25 },
		{ width: 8 },
		{ width: 25 },
		{ width: 25 },
		{ width: 30 },
		{ width: 40 },
	]

	// Add header row
	const headerRow = worksheet.addRow([
		t('Index'),
		t('Title'),
		t('Publisher'),
		t('Year'),
		t('Composer'),
		t('Arranger'),
		t('GEMA IDs'),
		t('Medley Contents'),
	])
	headerRow.font = { bold: true }

	// Add data rows for non-break entries
	let setlistIndex = 1
	for (const entry of entries) {
		if (isBreakEntry(entry)) {
			continue
		}

		const score = entry.scoreId !== null ? getScoreById(entry.scoreId) : undefined

		let title = ''
		let publisher = ''
		let year = ''
		let composer = ''
		let arranger = ''
		let gemaIds = ''
		let medleyContents = ''

		if (score) {
			title = score.title
			publisher = score.publisher ?? ''
			year = score.year !== null && score.year !== undefined ? String(score.year) : ''
			composer = score.composer ?? ''
			arranger = score.arranger ?? ''
			gemaIds = score.gemaIds ? score.gemaIds.join(', ') : ''
			medleyContents = score.medleyContents ? score.medleyContents.join(', ') : ''
		}

		worksheet.addRow([
			setlistIndex,
			title,
			publisher,
			year,
			composer,
			arranger,
			gemaIds,
			medleyContents,
		])

		setlistIndex++
	}

	// Generate and download the file
	const buffer = await workbook.xlsx.writeBuffer()
	const blob = new Blob([buffer], {
		type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	})
	const url = URL.createObjectURL(blob)

	const link = document.createElement('a')
	link.href = url
	// Sanitize filename: replace characters that are problematic in filenames
	// while preserving Unicode letters (including umlauts, accented chars, etc.)
	const sanitizedTitle = setlist.title.replace(/[<>:"/\\|?*]/g, '_')
	link.download = `${sanitizedTitle}.xlsx`
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	URL.revokeObjectURL(url)
}
