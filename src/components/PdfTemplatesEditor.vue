<template>
	<div class="pdf-templates">
		<p>
			{{ t('Shared templates for PDF setlist exports. The built-in standard template is always available.') }}
		</p>
		<ContentStateWrapper
			:loading="loading"
			:error="loadError"
			:isEmpty="templates.length === 0"
			:loadingText="t('Loading templates…')"
			:errorText="t('Could not load shared PDF templates.')"
			:emptyText="t('No shared templates have been created yet.')">
			<ul>
				<NcListItem
					v-for="item in templates"
					:key="item.id"
					:name="item.name"
					forceDisplayActions>
					<template v-if="item.description" #subname>
						{{ item.description }}
					</template>
					<template #actions>
						<NcActionButton
							:disabled="saving"
							@click="editTemplate(item)">
							<template #icon>
								<EditIcon :size="20" />
							</template>
							{{ t('Edit') }}
						</NcActionButton>
						<NcActionButton
							:disabled="saving"
							@click="removeTemplate(item)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('Delete') }}
						</NcActionButton>
					</template>
				</NcListItem>
			</ul>
		</ContentStateWrapper>
		<NcButton
			variant="secondary"
			:disabled="loading || saving"
			@click="newTemplate">
			<template #icon>
				<AddIcon :size="20" />
			</template>
			{{ t('New template') }}
		</NcButton>

		<NcDialog
			:open="editorOpen"
			:name="form.id ? t('Edit PDF template') : t('New PDF template')"
			@update:open="onDialogOpenChange">
			<div class="template-form">
				<NcTextField
					v-model="form.name"
					:label="t('Template name')"
					maxlength="160"
					required />
				<NcTextArea
					v-model="form.description"
					:label="t('Description (optional)')"
					:rows="2"
					resize="vertical" />
				<NcSelect
					:modelValue="headerPresetOptions.find((option) => option.value === headerPreset)"
					:options="headerPresetOptions"
					:inputLabel="t('Header layout')"
					:clearable="false"
					:searchable="false"
					@update:modelValue="headerPreset = $event?.value ?? headerPreset" />
				<fieldset>
					<legend>{{ t('Font sizes (pt)') }}</legend>
					<div class="field-grid">
						<NcTextField
							v-model.number="form.fontSizes.title"
							:label="t('Title')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
						<NcTextField
							v-model.number="form.fontSizes.subtitle"
							:label="t('Subtitle')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
						<NcTextField
							v-model.number="form.fontSizes.colorLegend"
							:label="t('Color legend')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
						<NcTextField
							v-model.number="form.fontSizes.columnTitle"
							:label="t('Column header')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
						<NcTextField
							v-model.number="form.fontSizes.scoreRow"
							:label="t('Score row')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
						<NcTextField
							v-model.number="form.fontSizes.breakRow"
							:label="t('Break row')"
							type="number"
							min="5"
							max="64"
							step="0.5" />
					</div>
				</fieldset>
				<fieldset>
					<legend>{{ t('Spacing (mm)') }}</legend>
					<div class="field-grid">
						<NcTextField
							v-model.number="form.margin"
							:label="t('Page margin')"
							type="number"
							min="0"
							max="50"
							step="0.5" />
						<NcTextField
							v-model.number="form.cellPadding"
							:label="t('Default cell padding')"
							type="number"
							min="0"
							max="20"
							step="0.5" />
					</div>
				</fieldset>
				<fieldset>
					<legend>{{ t('Row formatting rules') }}</legend>
					<p class="hint">
						{{ t('Rules are evaluated from top to bottom. The first matching rule wins.') }}
					</p>
					<div
						v-for="(rule, index) in form.rules"
						:key="rule.id"
						class="rule">
						<div class="rule-heading">
							<strong>{{ t('Rule') }} {{ index + 1 }}</strong>
							<div class="template-actions">
								<NcButton
									variant="tertiary"
									:aria-label="t('Move rule up')"
									:disabled="index === 0"
									@click="moveRule(index, -1)">
									<template #icon>
										<ArrowUpIcon :size="20" />
									</template>
								</NcButton>
								<NcButton
									variant="tertiary"
									:aria-label="t('Move rule down')"
									:disabled="index === form.rules.length - 1"
									@click="moveRule(index, 1)">
									<template #icon>
										<ArrowDownIcon :size="20" />
									</template>
								</NcButton>
								<NcButton
									variant="tertiary"
									:aria-label="t('Delete rule')"
									@click="form.rules.splice(index, 1)">
									<template #icon>
										<DeleteIcon :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<NcTextField
							v-model="rule.label"
							:label="t('Legend label (optional)')" />
						<NcSelect
							:modelValue="ruleKindOptions.find((option) => option.value === rule.kind)"
							:options="ruleKindOptions"
							:inputLabel="t('Match rows')"
							:clearable="false"
							:searchable="false"
							@update:modelValue="rule.kind = $event?.value ?? rule.kind" />
						<template v-if="rule.kind === 'tags'">
							<div class="rule-condition">
								<NcTextField
									v-model="rule.tag1"
									:label="t('Tag')"
									:placeholder="t('First tag')" />
								<NcSelect
									:modelValue="ruleOperatorOptions.find((option) => option.value === rule.operator)"
									:options="ruleOperatorOptions"
									:inputLabel="t('Combine')"
									:clearable="false"
									:searchable="false"
									@update:modelValue="rule.operator = $event?.value ?? ''" />
								<NcTextField
									v-if="rule.operator"
									v-model="rule.tag2"
									:label="t('Second tag')"
									:placeholder="t('Second tag')" />
							</div>
						</template>
						<div class="rule-formatting">
							<NcCheckboxRadioSwitch
								:modelValue="rule.backgroundColor !== null"
								type="switch"
								@update:modelValue="toggleRuleColor(rule)">
								{{ t('Background color') }}
							</NcCheckboxRadioSwitch>
							<NcColorPicker
								v-if="rule.backgroundColor !== null"
								:modelValue="rule.backgroundColor"
								:advancedFields="true"
								@update:modelValue="rule.backgroundColor = $event ?? null">
								<NcButton variant="secondary">
									<template #icon>
										<span
											class="color-swatch"
											:style="{ backgroundColor: rule.backgroundColor }" />
									</template>
									{{ rule.backgroundColor }}
								</NcButton>
							</NcColorPicker>
							<NcTextField
								:label="t('Cell padding override (mm, optional)')"
								:modelValue="rule.cellPadding ?? ''"
								type="number"
								min="0"
								max="20"
								step="0.5"
								:placeholder="t('Default')"
								@update:modelValue="setRulePadding(rule, $event)" />
						</div>
					</div>
					<NcButton
						variant="tertiary"
						@click="addRule">
						<template #icon>
							<AddIcon :size="20" />
						</template>
						{{ t('Add rule') }}
					</NcButton>
				</fieldset>
			</div>
			<template #actions>
				<NcButton
					:disabled="saving"
					@click="editorOpen = false">
					{{ t('Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="saving"
					@click="saveTemplate">
					<template #icon>
						<NcLoadingIcon
							v-if="saving"
							:size="20" />
						<ConfirmIcon
							v-else
							:size="20" />
					</template>
					{{ saving ? t('Saving…') : t('Save') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import type { SetlistTemplate, SetlistTemplateApiPostTemplatePayload } from '@/api/generated/openapi/data-contracts'
import type { PdfRuleDraft } from '@/utils/pdfexport/ruleEditor'

import { showError } from '@nextcloud/dialogs'
import { computed, onMounted, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { AddIcon, ArrowDownIcon, ArrowUpIcon, ConfirmIcon, DeleteIcon, EditIcon } from '@/icons/vue-material'
import { usePdfTemplatesStore } from '@/stores/pdfTemplatesStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { COMPACT_PDF_HEADER_INFO, DEFAULT_PDF_TEMPLATE, FULL_PDF_HEADER_INFO } from '@/utils/pdfexport/defaults'
import { newPdfRule, parsePdfTemplateRule, serializePdfRules } from '@/utils/pdfexport/ruleEditor'

type TemplateInput = SetlistTemplateApiPostTemplatePayload['template']

interface EditorForm {
	headerInfo: SetlistTemplate['headerInfo']
	id: number | null
	name: string
	description: string
	fontSizes: Required<NonNullable<TemplateInput['fontSizes']>>
	margin: number
	cellPadding: number
	rules: PdfRuleDraft[]
}

function emptyForm(): EditorForm {
	return {
		id: null,
		headerInfo: { ...FULL_PDF_HEADER_INFO, entries: [...FULL_PDF_HEADER_INFO.entries] },
		name: '',
		description: '',
		fontSizes: { ...DEFAULT_PDF_TEMPLATE.fontSizes },
		margin: DEFAULT_PDF_TEMPLATE.margin,
		cellPadding: DEFAULT_PDF_TEMPLATE.cellPadding,
		rules: [],
	}
}

const pdfTemplatesStore = usePdfTemplatesStore()
const templates = computed(() => pdfTemplatesStore.templates)
const loading = computed(() => pdfTemplatesStore.isLoading)
const loadError = ref(false)
const saving = ref(false)
const editorOpen = ref(false)
const form = ref<EditorForm>(emptyForm())

const ruleKindOptions = [
	{ value: 'tags', label: t('Score tags') },
	{ value: 'break', label: t('Break rows') },
]

const ruleOperatorOptions = [
	{ value: '', label: t('Single tag') },
	{ value: 'AND', label: 'AND' },
	{ value: 'OR', label: 'OR' },
	{ value: 'XOR', label: 'XOR' },
]

const headerPreset = computed({
	get(): 'full' | 'compact' | 'custom' {
		const { layout, entries } = form.value.headerInfo
		const matches = (preset: SetlistTemplate['headerInfo']) => layout === preset.layout && JSON.stringify(entries) === JSON.stringify(preset.entries)

		if (matches(FULL_PDF_HEADER_INFO)) {
			return 'full'
		}
		if (matches(COMPACT_PDF_HEADER_INFO)) {
			return 'compact'
		}
		return 'custom'
	},
	set(value: 'full' | 'compact' | 'custom') {
		if (value === 'custom') {
			return
		}
		const preset = value === 'compact' ? COMPACT_PDF_HEADER_INFO : FULL_PDF_HEADER_INFO
		form.value.headerInfo = { ...preset, entries: [...preset.entries] }
	},
})

onMounted(async () => {
	await tryShowError(
		() => pdfTemplatesStore.initialize(),
		t('Could not load shared PDF templates.'),
		() => { loadError.value = true },
	)
})

const headerPresetOptions = computed(() => [
	{ value: 'full', label: t('Full') },
	{ value: 'compact', label: t('Compact') },
	...(headerPreset.value === 'custom' ? [{ value: 'custom', label: t('Custom') }] : []),
])

function newTemplate(): void {
	form.value = emptyForm()
	editorOpen.value = true
}

function editTemplate(template: SetlistTemplate): void {
	let rules: PdfRuleDraft[]
	try {
		rules = [...template.rules].sort((a, b) => a.order - b.order).map(parsePdfTemplateRule)
	} catch {
		showError(t('Could not read PDF template rules.'))
		return
	}
	form.value = {
		id: template.id,
		headerInfo: { ...template.headerInfo, entries: [...template.headerInfo.entries] },
		name: template.name,
		description: template.description ?? '',
		fontSizes: { ...DEFAULT_PDF_TEMPLATE.fontSizes, ...template.fontSizes },
		margin: template.margin,
		cellPadding: template.cellPadding,
		rules,
	}
	editorOpen.value = true
}

function onDialogOpenChange(open: boolean): void {
	if (!saving.value) {
		editorOpen.value = open
	}
}

function addRule(): void {
	form.value.rules.push(newPdfRule())
}

function moveRule(index: number, delta: number): void {
	const target = index + delta
	if (target < 0 || target >= form.value.rules.length) {
		return
	}
	const [rule] = form.value.rules.splice(index, 1)
	if (rule) {
		form.value.rules.splice(target, 0, rule)
	}
}

function toggleRuleColor(rule: PdfRuleDraft): void {
	rule.backgroundColor = rule.backgroundColor === null ? '#FFE2B8' : null
}

function setRulePadding(rule: PdfRuleDraft, value: unknown): void {
	rule.cellPadding = value === null || value === undefined || String(value).trim() === ''
		? null
		: Number(value)
}

function numberInRange(value: unknown, min: number, max: number): boolean {
	return typeof value === 'number' && Number.isFinite(value) && value >= min && value <= max
}

function makePayload(): TemplateInput | null {
	const current = form.value
	if (!current.name.trim()) {
		showError(t('Please enter a template name.'))
		return null
	}
	if (Object.values(current.fontSizes).some((size) => !numberInRange(size, 5, 64))
		|| !numberInRange(current.margin, 0, 50) || !numberInRange(current.cellPadding, 0, 20)) {
		showError(t('Please check the font sizes and spacing values.'))
		return null
	}
	for (const rule of current.rules) {
		if (rule.kind === 'tags' && (!rule.tag1.trim() || (rule.operator && !rule.tag2.trim()))) {
			showError(t('Each tag rule must have the required tag names.'))
			return null
		}
		if (rule.cellPadding !== null && !numberInRange(rule.cellPadding, 0, 20)) {
			showError(t('Invalid rule cell padding.'))
			return null
		}
	}
	return {
		name: current.name.trim(),
		headerInfo: current.headerInfo,
		description: current.description.trim() || null,
		fontSizes: { ...current.fontSizes },
		margin: current.margin,
		cellPadding: current.cellPadding,
		rules: serializePdfRules(current.rules),
	}
}

async function saveTemplate(): Promise<void> {
	if (saving.value) {
		return
	}
	const payload = makePayload()
	if (!payload) {
		return
	}
	saving.value = true
	try {
		if (form.value.id !== null) {
			await pdfTemplatesStore.updateTemplate(form.value.id, payload)
		} else {
			await pdfTemplatesStore.createTemplate(payload)
		}
		editorOpen.value = false
	} catch {
		showError(t('Could not save PDF template.'))
	} finally {
		saving.value = false
	}
}

async function removeTemplate(item: SetlistTemplate): Promise<void> {
	if (!window.confirm(t('Delete template "{name}"? Setlists using it will revert to the standard template.', { name: item.name }))) {
		return
	}
	saving.value = true
	try {
		await pdfTemplatesStore.deleteTemplate(item.id)
	} catch {
		showError(t('Could not delete PDF template.'))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.pdf-templates,
.template-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.template-form {
	max-height: 65vh;
	overflow-y: auto;
	padding: 4px;
}

.template-form fieldset {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 12px;
}

.field-grid,
.rule-condition {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
	gap: 10px;
}

.rule {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding-block: 12px;
	border-bottom: 1px solid var(--color-border);
}

.rule-heading,
.template-actions,
.rule-formatting {
	display: flex;
	align-items: center;
	gap: 8px;
}

.rule-heading {
	justify-content: space-between;
}

.rule-formatting {
	flex-wrap: wrap;
}

.color-swatch {
	width: 20px;
	height: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}
</style>
