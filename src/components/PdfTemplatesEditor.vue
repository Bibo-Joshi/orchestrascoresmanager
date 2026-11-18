<template>
	<div class="pdf-templates">
		<p>
			{{ t('Shared templates for PDF setlist exports. The built-in standard template is always available.') }}
		</p>
		<p v-if="loading">
			{{ t('Loading templates…') }}
		</p>
		<p
			v-else-if="templates.length === 0"
			class="hint">
			{{ t('No shared templates have been created yet.') }}
		</p>
		<ul
			v-else
			class="template-list">
			<li
				v-for="item in templates"
				:key="item.id"
				class="template-item">
				<div class="template-info">
					<strong>{{ item.name }}</strong>
					<p v-if="item.description">
						{{ item.description }}
					</p>
				</div>
				<div class="template-actions">
					<NcButton
						variant="tertiary"
						:disabled="saving"
						@click="editTemplate(item)">
						<template #icon>
							<EditIcon :size="20" />
						</template>
						{{ t('Edit') }}
					</NcButton>
					<NcButton
						variant="tertiary"
						:disabled="saving"
						@click="removeTemplate(item)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('Delete') }}
					</NcButton>
				</div>
			</li>
		</ul>
		<NcButton
			class="new-template"
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
				<label>
					{{ t('Template name') }}
					<input
						v-model="form.name"
						type="text"
						maxlength="160"
						required>
				</label>
				<label>
					{{ t('Description (optional)') }}
					<textarea
						v-model="form.description"
						maxlength="2000"
						rows="2" />
				</label>
				<fieldset>
					<legend>{{ t('Font sizes (pt)') }}</legend>
					<div class="field-grid">
						<label>
							{{ t('Title') }}
							<input
								v-model.number="form.fontSizes.title"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
						<label>
							{{ t('Subtitle') }}
							<input
								v-model.number="form.fontSizes.subtitle"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
						<label>
							{{ t('Color legend') }}
							<input
								v-model.number="form.fontSizes.colorLegend"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
						<label>
							{{ t('Column header') }}
							<input
								v-model.number="form.fontSizes.columnTitle"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
						<label>
							{{ t('Score row') }}
							<input
								v-model.number="form.fontSizes.scoreRow"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
						<label>
							{{ t('Break row') }}
							<input
								v-model.number="form.fontSizes.breakRow"
								type="number"
								min="5"
								max="64"
								step="0.5">
						</label>
					</div>
				</fieldset>
				<fieldset>
					<legend>{{ t('Spacing (mm)') }}</legend>
					<div class="field-grid">
						<label>
							{{ t('Page margin') }}
							<input
								v-model.number="form.margin"
								type="number"
								min="0"
								max="50"
								step="0.5">
						</label>
						<label>
							{{ t('Default cell padding') }}
							<input
								v-model.number="form.cellPadding"
								type="number"
								min="0"
								max="20"
								step="0.5">
						</label>
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
						<label>
							{{ t('Legend label (optional)') }}
							<input
								v-model="rule.label"
								type="text"
								maxlength="160">
						</label>
						<label>
							{{ t('Match rows') }}
							<select
								v-model="rule.kind"
								:disabled="rule.kind === 'unsupported'">
								<option value="tags">{{ t('Score tags') }}</option>
								<option value="break">{{ t('Break rows') }}</option>
								<option
									v-if="rule.kind === 'unsupported'"
									value="unsupported">{{ t('Advanced condition (preserved)') }}</option>
							</select>
						</label>
						<template v-if="rule.kind === 'tags'">
							<div class="rule-condition">
								<label>
									{{ t('Tag') }}
									<input
										v-model="rule.tag1"
										type="text"
										maxlength="160"
										:placeholder="t('First tag')">
								</label>
								<label>
									{{ t('Combine') }}
									<select v-model="rule.operator">
										<option value="">{{ t('Single tag') }}</option>
										<option value="AND">AND</option>
										<option value="OR">OR</option>
										<option value="XOR">XOR</option>
									</select>
								</label>
								<label v-if="rule.operator">
									{{ t('Second tag') }}
									<input
										v-model="rule.tag2"
										type="text"
										maxlength="160"
										:placeholder="t('Second tag')">
								</label>
							</div>
						</template>
						<p
							v-else-if="rule.kind === 'unsupported'"
							class="hint">
							{{ t('This condition cannot be edited here; it will be kept unchanged.') }}
						</p>
						<div class="rule-formatting">
							<label class="checkbox-field">
								<input
									type="checkbox"
									:checked="rule.backgroundColor !== null"
									@change="toggleRuleColor(rule)">
								{{ t('Background color') }}
							</label>
							<input
								v-if="rule.backgroundColor !== null"
								v-model="rule.backgroundColor"
								type="color"
								:aria-label="t('Background color')">
							<label>
								{{ t('Cell padding override (mm, optional)') }}
								<input
									:value="rule.cellPadding ?? ''"
									type="number"
									min="0"
									max="20"
									step="0.5"
									:placeholder="t('Default')"
									@input="setRulePadding(rule, $event)">
							</label>
						</div>
					</div>
					<NcButton
						variant="tertiary"
						:disabled="form.rules.length >= 30"
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
import type { PdfRuleDraft } from '@/utils/pdfTemplateRules'
import type { PdfTemplate, PdfTemplateInput } from '@/utils/pdfTemplatesApi'

import { showError } from '@nextcloud/dialogs'
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { AddIcon, ArrowDownIcon, ArrowUpIcon, ConfirmIcon, DeleteIcon, EditIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'
import { editPdfRule, newPdfRule, serializePdfRules } from '@/utils/pdfTemplateRules'
import { createPdfTemplate, deletePdfTemplate, fetchPdfTemplates, updatePdfTemplate } from '@/utils/pdfTemplatesApi'

interface EditorForm {
	id: string | null
	name: string
	description: string
	fontSizes: Required<PdfTemplateInput['fontSizes']>
	margin: number
	cellPadding: number
	rules: PdfRuleDraft[]
}

const DEFAULT_FONTS: Required<PdfTemplateInput['fontSizes']> = {
	title: 16,
	subtitle: 11,
	colorLegend: 8,
	columnTitle: 9,
	scoreRow: 8,
	breakRow: 8,
}

function emptyForm(): EditorForm {
	return {
		id: null,
		name: '',
		description: '',
		fontSizes: { ...DEFAULT_FONTS },
		margin: 8,
		cellPadding: 1.5,
		rules: [],
	}
}

const templates = ref<PdfTemplate[]>([])
const loading = ref(false)
const saving = ref(false)
const editorOpen = ref(false)
const form = ref<EditorForm>(emptyForm())

async function refreshTemplates(): Promise<void> {
	loading.value = true
	try {
		templates.value = await fetchPdfTemplates()
	} catch {
		showError(t('Could not load shared PDF templates.'))
	} finally {
		loading.value = false
	}
}

onMounted(refreshTemplates)

function newTemplate(): void {
	form.value = emptyForm()
	editorOpen.value = true
}

function editTemplate(template: PdfTemplate): void {
	form.value = {
		id: template.id,
		name: template.name,
		description: template.description ?? '',
		fontSizes: { ...DEFAULT_FONTS, ...template.fontSizes },
		margin: template.margin,
		cellPadding: template.cellPadding,
		rules: [...template.rules].sort((a, b) => a.order - b.order).map(editPdfRule),
	}
	editorOpen.value = true
}

function onDialogOpenChange(open: boolean): void {
	if (!saving.value) {
		editorOpen.value = open
	}
}

function addRule(): void {
	if (form.value.rules.length < 30) {
		form.value.rules.push(newPdfRule())
	}
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

function setRulePadding(rule: PdfRuleDraft, event: Event): void {
	const input = event.target as HTMLInputElement
	rule.cellPadding = input.value.trim() === '' ? null : Number(input.value)
}

function numberInRange(value: unknown, min: number, max: number): boolean {
	return typeof value === 'number' && Number.isFinite(value) && value >= min && value <= max
}

function makePayload(): PdfTemplateInput | null {
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
		if (form.value.id) {
			await updatePdfTemplate(form.value.id, payload)
		} else {
			await createPdfTemplate(payload)
		}
		editorOpen.value = false
		await refreshTemplates()
	} catch {
		showError(t('Could not save PDF template.'))
	} finally {
		saving.value = false
	}
}

async function removeTemplate(item: PdfTemplate): Promise<void> {
	if (!window.confirm(t('Delete template "{name}"? Setlists using it will revert to the standard template.', { name: item.name }))) {
		return
	}
	saving.value = true
	try {
		await deletePdfTemplate(item.id)
		await refreshTemplates()
	} catch {
		showError(t('Could not delete PDF template.'))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.pdf-templates { display: flex; flex-direction: column; gap: 12px; }
.template-list { padding: 0; margin: 0; list-style: none; }
.template-item { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--color-border); }
.template-info { flex: 1; min-width: 150px; overflow-wrap: anywhere; }
.template-info p { margin: 3px 0; color: var(--color-text-maxcontrast); }
.template-actions { display: flex; align-items: center; gap: 4px; }
.new-template { width: 100%; }
.template-form { display: flex; flex-direction: column; gap: 16px; max-height: 65vh; overflow-y: auto; padding: 4px; }
.template-form label { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.template-form input:not([type='checkbox']):not([type='color']), .template-form textarea, .template-form select { width: 100%; min-width: 0; box-sizing: border-box; padding: 7px; border: 1px solid var(--color-border); border-radius: var(--border-radius); }
.template-form fieldset { border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 12px; }
.template-form legend { font-weight: 600; padding: 0 4px; }
.field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.rule { margin: 12px 0; padding: 12px; border: 1px solid var(--color-border); border-radius: var(--border-radius); display: flex; flex-direction: column; gap: 10px; }
.rule-heading { display: flex; align-items: center; justify-content: space-between; }
.rule-condition { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; }
.rule-formatting { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.rule-formatting > label:last-child { flex: 1 1 160px; }
.template-form label.checkbox-field { flex-direction: row; align-items: center; }
.hint { color: var(--color-text-maxcontrast); font-size: 0.9em; }
</style>
