/**
 * XLSX exporter for setlist GEMA report.
 * Generates an XLSX file with the setlist entries for GEMA reporting.
 */
import { Workbook } from 'exceljs'
import { linkTo } from '@nextcloud/router'
import type { Setlist, SetlistEntry, Score } from '@/api/generated/openapi/data-contracts'
import { isBreakEntry } from '@/utils/setlistScoreUtils'

const TEMPLATE_URL = linkTo('orchestrascoresmanager', 'public/gema-template.xlsx')
const DATA_START_ROW = 21

interface PersonNameParts {
	firstName: string
	lastName: string
}

/**
 * Split a person name into first and last name using the final space as separator.
 * If no separator exists, the full value is treated as last name.
 * @param rawName - The raw name string to split
 * @return An object containing the first and last name parts
 */
function splitPersonName(rawName: string | null | undefined): PersonNameParts {
	const normalizedName = (rawName ?? '').trim()
	if (normalizedName.length === 0) {
		return { firstName: '', lastName: '' }
	}

	const lastSpaceIndex = normalizedName.lastIndexOf(' ')
	if (lastSpaceIndex < 0) {
		return { firstName: '', lastName: normalizedName }
	}

	return {
		firstName: normalizedName.slice(0, lastSpaceIndex).trim(),
		lastName: normalizedName.slice(lastSpaceIndex + 1).trim(),
	}
}

/**
 * Split potential multiple composers by comma and return primary/secondary names.
 * @param rawComposer - The raw composer string which may contain multiple names separated by commas
 * @return An object containing the primary and secondary composer name parts
 */
function splitComposers(rawComposer: string | null | undefined): { primary: PersonNameParts; secondary: PersonNameParts } {
	const composers = (rawComposer ?? '')
		.split(',')
		.map((value) => value.trim())
		.filter((value) => value.length > 0)

	const primary = splitPersonName(composers[0] ?? '')
	const secondary = splitPersonName(composers[1] ?? '')

	return { primary, secondary }
}

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
	const response = await fetch(TEMPLATE_URL)
	if (!response.ok) {
		throw new Error(`Failed to load GEMA template from ${TEMPLATE_URL}`)
	}

	const templateArrayBuffer = await response.arrayBuffer()
	await workbook.xlsx.load(templateArrayBuffer)

	const worksheet = workbook.worksheets[0]
	if (!worksheet) {
		throw new Error('GEMA template worksheet is missing')
	}

	let targetRowNumber = DATA_START_ROW
	for (const entry of entries) {
		if (isBreakEntry(entry)) {
			continue
		}

		const score = entry.scoreId !== null ? getScoreById(entry.scoreId) : undefined
		const currentRow = worksheet.getRow(targetRowNumber)

		const gemaIds = score?.gemaIds ? score.gemaIds.join(', ') : ''
		const title = score?.title ?? ''
		const publisher = score?.publisher ?? ''
		const { primary: primaryComposer, secondary: secondaryComposer } = splitComposers(score?.composer)
		const arranger = splitPersonName(score?.arranger)

		currentRow.getCell('A').value = gemaIds
		currentRow.getCell('B').value = title
		currentRow.getCell('F').value = primaryComposer.lastName
		currentRow.getCell('G').value = primaryComposer.firstName
		currentRow.getCell('J').value = publisher
		currentRow.getCell('P').value = arranger.lastName
		currentRow.getCell('Q').value = arranger.firstName
		currentRow.getCell('R').value = secondaryComposer.lastName
		currentRow.getCell('S').value = secondaryComposer.firstName
		currentRow.commit()

		targetRowNumber++
	}

	const buffer = await workbook.xlsx.writeBuffer()
	const blob = new Blob([buffer], {
		type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	})
	const url = URL.createObjectURL(blob)

	const link = document.createElement('a')
	link.href = url
	const sanitizedTitle = setlist.title.replace(/[<>:"/\\|?*]/g, '_')
	link.download = `${sanitizedTitle}.xlsx`
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	URL.revokeObjectURL(url)
}
