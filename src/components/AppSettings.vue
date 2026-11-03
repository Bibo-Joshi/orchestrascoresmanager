<template>
	<NcAppSettingsDialog v-model:open="open" :name="t('Settings')">
		<NcAppSettingsSection id="setlists" :name="t('Setlists')">
			<NcFormGroup hideLabel>
				<NcTextField
					v-model="selectedDefaultModerationTimeString"
					:label="t('Default Moderation Time (HH:MM:SS)')"
					:placeholder="t('e.g., 0:30 or 1:30:00')"
					@input="restrictToTimeFormat"
					@paste="restrictToTimeFormat" />

				<NcSelect
					v-model="selectedFolderCollection"
					:options="activeFolderCollectionOptions"
					:inputLabel="t('Default Folder Collection')"
					:placeholder="t('Select folder collection')"
					:clearable="true"
					label="label"
					trackBy="value" />

				<div class="save-button-container">
					<NcButton
						variant="secondary"
						:disabled="isSaving"
						@click="handleSave">
						<template #icon>
							<NcLoadingIcon v-if="isSaving" :size="20" />
							<ConfirmIcon v-else :size="20" />
						</template>
						{{ isSaving ? t('Saving …') : t('Save') }}
					</NcButton>
				</div>
			</NcFormGroup>
		</NcAppSettingsSection>
		<NcAppSettingsSection id="pdf-layouts" name="PDF-Layouts">
			<div class="pdf-profile-heading">
				<p>PDF-Vorlagen für den Setlistenexport. Das Standardlayout des Entwicklers bleibt unverändert.</p>
				<NcButton variant="secondary" @click="addPdfProfile">
					+ Layout
				</NcButton>
			</div>
			<div class="pdf-standard-profile">
				🔒 Standardlayout (Original, nicht veränderbar)
			</div>
			<details v-for="profile in pdfProfiles" :key="profile.id" class="pdf-profile">
				<summary class="pdf-profile-summary">
					<span>{{ profile.name }}</span>
				</summary>
				<div class="pdf-profile-content">
					<div class="pdf-field">
						<label :for="`profile-name-${profile.id}`">Layoutname</label>
						<input :id="`profile-name-${profile.id}`"
							v-model="profile.name"
							type="text"
							maxlength="80">
					</div>
					<div class="pdf-numeric-grid">
						<label>Überschrift (pt)<input v-model.number="profile.titleFontSize"
							type="number"
							min="8"
							max="36"
							step="0.5"></label>
						<label>Abschnitt (pt)<input v-model.number="profile.sectionFontSize"
							type="number"
							min="7"
							max="24"
							step="0.5"></label>
						<label>Tabellenschrift (pt)<input v-model.number="profile.tableFontSize"
							type="number"
							min="6"
							max="18"
							step="0.5"></label>
						<label>Seitenrand (mm)<input v-model.number="profile.margin"
							type="number"
							min="3"
							max="30"
							step="0.5"></label>
						<label>Zellenabstand (mm)<input v-model.number="profile.cellPadding"
							type="number"
							min="0.3"
							max="8"
							step="0.5"></label>
						<label>Pausenabstand (mm)<input v-model.number="profile.breakPadding"
							type="number"
							min="1"
							max="10"
							step="0.5"></label>
						<label>Pausenfarbe<input v-model="profile.breakColor" type="color"></label>
					</div>
					<h4>Farbregeln nach Schlagworten</h4>
					<p class="pdf-hint">
						Die erste passende Regel gewinnt. &amp; bedeutet UND, | bedeutet ODER.
					</p>
					<div v-for="(rule, index) in profile.rules" :key="rule.id" class="pdf-rule">
						<input v-model="rule.label"
							aria-label="Bezeichnung"
							title="Legendenbezeichnung"
							maxlength="60"
							placeholder="Male">
						<input v-model="rule.expression"
							aria-label="Schlagwortkombination"
							title="Schlagwortkombination"
							maxlength="240"
							placeholder="Male & Female | Duett">
						<input v-model="rule.color" aria-label="Farbe" type="color">
						<button type="button"
							aria-label="Regel nach oben"
							:disabled="index === 0"
							@click="movePdfRule(profile, index, -1)">
							↑
						</button>
						<button type="button"
							aria-label="Regel nach unten"
							:disabled="index === profile.rules.length - 1"
							@click="movePdfRule(profile, index, 1)">
							↓
						</button>
						<button type="button" aria-label="Regel entfernen" @click="profile.rules.splice(index, 1)">
							−
						</button>
					</div>
					<div class="pdf-profile-actions">
						<NcButton variant="secondary" @click="addPdfRule(profile)">
							+ Farbregel
						</NcButton>
						<NcButton variant="tertiary" @click="duplicatePdfProfile(profile)">
							Layout duplizieren
						</NcButton>
						<NcButton variant="tertiary" @click="removePdfProfile(profile)">
							− Layout entfernen
						</NcButton>
					</div>
				</div>
			</details>
			<div class="save-button-container">
				<NcButton variant="primary" :disabled="isSavingPdfProfiles" @click="savePdfProfiles">
					{{ isSavingPdfProfiles ? 'Speichere …' : 'PDF-Layouts speichern' }}
				</NcButton>
			</div>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script setup lang="ts">
import type { PdfLayoutProfile } from '@/utils/pdfLayoutSettings'

import { showError } from '@nextcloud/dialogs'
import { computed, defineModel, onMounted, ref } from 'vue'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ConfirmIcon } from '@/icons/vue-material'
import { useAppSettingsStore } from '@/stores/appSettingsStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { cloneProfile, DEFAULT_PDF_PROFILE } from '@/utils/pdfLayoutSettings'
import { fetchPdfProfiles, putPdfProfiles } from '@/utils/pdfProfilesApi'
import {
	formatDurationHHMMSS,
	parseDurationHHMMSS,
	restrictToTimeFormat as restrictInputToTimeFormat,
} from '@/utils/timeFormatUtils'
const open = defineModel<boolean>({ default: false })
const pdfProfiles = ref<PdfLayoutProfile[]>([])
const isSavingPdfProfiles = ref(false)

const appSettingsStore = useAppSettingsStore()
const folderCollectionsStore = useFolderCollectionsStore()

const isSaving = ref(false)

const activeFolderCollectionOptions = computed((): Array<{ label: string, value: number }> => {
	return folderCollectionsStore.folderCollectionsSorted
		.filter((collection) => collection.activeVersionId !== null)
		.map((collection) => ({
			label: collection.title,
			value: collection.id,
		}))
})

const selectedDefaultModerationTimeString = ref<string>('')
const selectedFolderCollection = ref<{ label: string, value: number } | null>(null)

onMounted(async () => {
	await Promise.all([
		folderCollectionsStore.initialize(),
		appSettingsStore.initialize(),
		fetchPdfProfiles().then((profiles) => { pdfProfiles.value = profiles }),
	])

	selectedDefaultModerationTimeString.value = appSettingsStore.defaultModerationTime !== null
		? formatDurationHHMMSS(appSettingsStore.defaultModerationTime)
		: ''
	selectedFolderCollection.value = (() => {
		const id = appSettingsStore.defaultFolderCollectionId
		if (id === null) {
			return null
		}
		return activeFolderCollectionOptions.value.find((option) => option.value === id) ?? null
	})()
})

/**
 * Restricts moderation time input to time-compatible characters.
 *
 * @param event Input or paste event from the text field.
 */
function restrictToTimeFormat(event: Event) {
	restrictInputToTimeFormat(event)
}

/**
 * Saves app settings using placeholder store implementation.
 */
async function handleSave() {
	// Validate and parse times
	let defaultModerationDuration: number | null = null

	try {
		if (selectedDefaultModerationTimeString.value.trim()) {
			defaultModerationDuration = parseDurationHHMMSS(selectedDefaultModerationTimeString.value)
		}
	} catch {
		showError(t('Invalid default moderation time format. Use (HH:)MM:SS'))
		return
	}

	await tryShowError(async () => {
		isSaving.value = true
		await appSettingsStore.updateSettingsPlaceholder(
			defaultModerationDuration,
			selectedFolderCollection.value ? selectedFolderCollection.value.value : null,
		)
	}, t('Failed to save settings'))
	isSaving.value = false
}

function createPdfId(): string {
	return `layout-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`
}

function addPdfProfile(): void {
	if (pdfProfiles.value.length >= 12) {
		showError('Maximal 12 PDF-Layouts möglich.')
		return
	}
	const profile = cloneProfile(DEFAULT_PDF_PROFILE)
	profile.id = createPdfId()
	profile.name = 'Neues Layout'
	pdfProfiles.value.push(profile)
}

function duplicatePdfProfile(source: PdfLayoutProfile): void {
	if (pdfProfiles.value.length >= 12) {
		showError('Maximal 12 PDF-Layouts möglich.')
		return
	}
	const duplicate = cloneProfile(source)
	duplicate.id = createPdfId()
	duplicate.name = `${source.name} (Kopie)`
	duplicate.rules.forEach((rule) => {
		rule.id = createPdfId()
	})

	pdfProfiles.value.push(duplicate)
}

function removePdfProfile(profile: PdfLayoutProfile): void {
	if (!window.confirm(`Layout „${profile.name}“ wirklich entfernen?`)) {
		return
	}
	pdfProfiles.value = pdfProfiles.value.filter((p) => p.id !== profile.id)
}

function addPdfRule(profile: PdfLayoutProfile): void {
	if (profile.rules.length >= 30) {
		showError('Maximal 30 Farbregeln pro Layout möglich.')
		return
	}
	profile.rules.push({ id: createPdfId(), label: 'Neue Regel', expression: '', color: '#FFF1BC' })
}

function movePdfRule(profile: PdfLayoutProfile, index: number, delta: number): void {
	const next = index + delta
	if (next < 0 || next >= profile.rules.length) {
		return
	}
	const [rule] = profile.rules.splice(index, 1)
	if (rule) {
		profile.rules.splice(next, 0, rule)
	}
}

async function savePdfProfiles(): Promise<void> {
	if (pdfProfiles.value.some((p) => !p.name.trim() || p.rules.some((r) => !r.label.trim() || !r.expression.trim()))) {
		showError('Bitte alle Layoutnamen sowie die Bezeichnungen und Ausdrücke der Farbregeln ausfüllen.')
		return
	}
	isSavingPdfProfiles.value = true
	try {
		pdfProfiles.value = await putPdfProfiles(pdfProfiles.value)
	} catch {
		showError('Konnte PDF-Layouts nicht speichern.')
	} finally {
		isSavingPdfProfiles.value = false
	}
}

</script>

<style lang="scss" scoped>
.save-button-container {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
	padding-top: 16px;
}

.pdf-profile-heading, .pdf-profile-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pdf-profile-heading { justify-content: space-between; margin-bottom: 12px; }
.pdf-profile-heading p { margin: 0; }
.pdf-standard-profile { padding: 12px; background: var(--color-background-dark); border-radius: 8px; }
.pdf-profile { border: 1px solid var(--color-border); border-radius: 8px; margin-top: 8px; }
.pdf-profile-summary { padding: 12px; cursor: pointer; font-weight: 600; }
.pdf-profile-content { padding: 0 14px 14px; }
.pdf-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.pdf-numeric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.pdf-numeric-grid label { display: flex; flex-direction: column; gap: 5px; }
.pdf-numeric-grid input, .pdf-field input, .pdf-rule input { min-width: 0; padding: 6px; border: 1px solid var(--color-border); border-radius: 6px; }
.pdf-numeric-grid input[type='color'], .pdf-rule input[type='color'] { width: 50px; min-width: 50px; height: 36px; padding: 1px; }
.pdf-rule { display: grid; grid-template-columns: minmax(100px, 1fr) minmax(160px, 2fr) 50px repeat(3, 30px); gap: 6px; margin: 6px 0; }
.pdf-rule button { background: var(--color-background-dark); border: 1px solid var(--color-border); border-radius: 6px; cursor: pointer; }
.pdf-rule button:disabled { opacity: 0.4; cursor: default; }
.pdf-hint { font-size: 0.85em; color: var(--color-text-maxcontrast); }
.pdf-profile-actions { margin-top: 16px; }

</style>
