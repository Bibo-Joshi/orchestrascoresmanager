import { formatDurationHHMMSS, parseDurationHHMMSS } from './timeFormatUtils'
import type { ColDef } from 'ag-grid-community'
import { t } from './l10n'

/**
 * Create a column definition for a duration field with (HH:)MM:SS format
 * @param field - The field name
 * @param headerName - The column header name
 * @param editable - Whether the column should be editable (default: true)
 * @param allowEmpty - Whether empty values are allowed when editable (default: true)
 * @return Column definition for ag-grid
 */
export function createDurationColumn(field: string, headerName: string, editable = true, allowEmpty = true): ColDef {
	const baseColumn: ColDef = {
		field,
		headerName,
		valueFormatter: (params) => {
			if (params.value === null || params.value === undefined || isNaN(params.value as number)) {
				return undefined
			}
			return formatDurationHHMMSS(params.value as number)
		},
		filter: 'agNumberColumnFilter',
		filterParams: {
			allowedCharPattern: ':0-9',
			numberParser: (text: string) => {
				try {
					const parsed = parseDurationHHMMSS(text)
					return parsed !== null ? parsed : NaN
				} catch {
					return NaN
				}
			},
			numberFormatter: (value: number) => {
				if (value === null || value === undefined || isNaN(value)) {
					return ''
				}
				return formatDurationHHMMSS(value)
			},
		},
	}

	if (editable) {
		return {
			...baseColumn,
			cellDataType: 'text',
			cellEditor: 'agTextCellEditor',
			cellEditorParams: {
				useFormatter: true,
				getValidationErrors: (params: { value: string }) => {
					const value = params.value
					if (value === null || value === undefined || value === '') {
						if (allowEmpty) {
							return null
						}
						return [t('Value must not be empty')]
					}
					try {
						parseDurationHHMMSS(value)
					} catch (e) {
						return [t('Invalid duration format. Use (HH:)MM:SS')]
					}
					return null
				},
			},
		}
	}

	return baseColumn
}
