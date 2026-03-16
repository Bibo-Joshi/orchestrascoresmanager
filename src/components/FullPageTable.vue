<template>
	<div ref="tableContainer" class="full-page-table" :data-ag-theme-mode="themeMode">
		<AgGridVue
			:row-data="props.data"
			:column-defs="props.columnDefs"
			:default-col-def="defaultColDef"
			:grid-options="gridOptions"
			:style="{ height: '100%', width: '100%' }"
			:theme="nextcloudTheme"
			:locale-text="localeText"
			invalid-edit-value-mode="block"
			@grid-ready="onGridReady" />
	</div>
</template>

<script setup lang="ts">
import { isDarkTheme } from '@nextcloud/vue/functions/isDarkTheme'
import { ref, defineProps, watch, defineEmits, defineExpose } from 'vue'
import { AgGridVue } from 'ag-grid-vue3'
import { ModuleRegistry, AllCommunityModule, LocaleModule } from 'ag-grid-community'
import type { ColDef, GridOptions, GridApi, GridReadyEvent } from 'ag-grid-community'
import { nextcloudTheme } from '../utils/agGridTheme'
import { getAgGridLocaleText } from '../utils/agGridLocale'

// Register AG Grid modules (required for v34+)
ModuleRegistry.registerModules([AllCommunityModule, LocaleModule])

const themeMode = ref<string>(isDarkTheme ? 'dark' : 'light')

// Get AG Grid locale texts based on Nextcloud language
const localeText = getAgGridLocaleText()

// Define component props with TypeScript interfaces
interface TableData {
	[key: string]: unknown
}

interface Props {
	data: TableData[]
	columnDefs: ColDef[]
	editable: boolean
	rowDragManaged?: boolean
	context?: Record<string, unknown>
}

const props = defineProps<Props>()
const emit = defineEmits(['cell-value-changed', 'cell-double-clicked', 'row-drag-end'] as const)

// Container ref for dynamic sizing
const tableContainer = ref<HTMLElement>()

// Grid API refs (filled on grid ready)
const gridApi = ref<GridApi | null>(null)

// Grid options for ag-grid configuration
const gridOptions: GridOptions = {
	// Enable features
	animateRows: true,
	ensureDomOrder: true,

	// Context for cell renderers
	context: props.context || {},

	// Row dragging
	rowDragManaged: props.rowDragManaged || false,

	// Column Sizing
	autoSizeStrategy: {
		type: 'fitCellContents',
		skipHeader: false,
	},

	// Responsive behavior
	suppressColumnVirtualisation: false,
	suppressRowVirtualisation: false,

	// Performance optimizations
	debounceVerticalScrollbar: true,
	suppressAnimationFrame: false,

	// Editing configuration
	suppressClickEdit: false,
	singleClickEdit: false,

	// Emit cell value changes to parent so it can persist via API
	onCellValueChanged: (event) => {
		emit('cell-value-changed', event)
	},

	// Emit cell double-click events to parent for custom handling (e.g., opening dialogs)
	onCellDoubleClicked: (event) => {
		emit('cell-double-clicked', event)
	},

	// Emit row drag end events to parent
	onRowDragEnd: (event) => {
		emit('row-drag-end', event)
	},
}

// Default column definition
const defaultColDef: ColDef = {
	resizable: true,
	sortable: true,
	filter: true,
	suppressMovable: false,
	editable: props.editable,
}

// Grid ready handler: store APIs for later actions (like export)
function onGridReady(params: GridReadyEvent) {
	gridApi.value = params.api
}

// Export table as CSV. Only export columns that define a `field` (this excludes EditButton column).
function exportAsCsv(fileName?: string): boolean {
	if (!gridApi.value) throw new Error('Grid API not initialized')
	const columnKeys = (props.columnDefs || [])
		.filter((c) => !!(c as ColDef).field)
		.map((c) => String((c as ColDef).field))

	gridApi.value.exportDataAsCsv({
		fileName: fileName || 'orchestrascores.csv',
		columnKeys,
	})
	return true
}

/**
 * Returns the AG Grid API instance, or null if the grid is not yet initialised.
 */
function getGridApi(): GridApi | null {
	return gridApi.value
}

// Expose export function and grid API to parent components via ref
defineExpose({ exportAsCsv, getGridApi })

// Watch for data changes and update grid
watch(() => props.data, () => {
	// Grid will automatically update when rowData changes
}, { deep: true })

// Watch for column definition changes
watch(() => props.columnDefs, () => {
	// Grid will automatically update when columnDefs changes
}, { deep: true })
</script>

<style lang="scss" scoped>
.full-page-table {
	height: 100%;
	width: 100%;
}
</style>
