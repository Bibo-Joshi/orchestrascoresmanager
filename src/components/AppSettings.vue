<template>
	<NcAppSettingsDialog v-model:open="open" :name="t('Settings')">
		<NcAppSettingsSection id="setlists" :name="t('Setlists')">
			<NcFormGroup hide-label>
				<NcTextField
					v-model="selectedDefaultModerationTimeString"
					:label="t('Default Moderation Time (HH:MM:SS)')"
					:placeholder="t('e.g., 0:30 or 1:30:00')"
					@input="restrictToTimeFormat"
					@paste="restrictToTimeFormat" />

				<NcSelect
					v-model="selectedFolderCollection"
					:options="activeFolderCollectionOptions"
					:input-label="t('Default Folder Collection')"
					:placeholder="t('Select folder collection')"
					:clearable="true"
					label="label"
					track-by="value" />

				<div class="save-button-container">
					<NcButton
						variant="secondary"
						:disabled="isSaving"
						@click="handleSave">
						<template #icon>
							<NcLoadingIcon v-if="isSaving" :size="20" />
							<ConfirmIcon v-else :size="20" />
						</template>
						{{ isSaving ? t('Saving …') : t('Save') }}
					</NcButton>
				</div>
			</NcFormGroup>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script setup lang="ts">
import { computed, defineModel, onMounted, ref } from 'vue'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { ConfirmIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'
import {
	formatDurationHHMMSS, parseDurationHHMMSS,
	restrictToTimeFormat as restrictInputToTimeFormat,
} from '@/utils/timeFormatUtils'
import { useAppSettingsStore } from '@/stores/appSettingsStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { tryShowError } from '@/utils/errorHandling'
import { showError } from '@nextcloud/dialogs'

const open = defineModel<boolean>({ default: false })
const appSettingsStore = useAppSettingsStore()
const folderCollectionsStore = useFolderCollectionsStore()

const isSaving = ref(false)

const activeFolderCollectionOptions = computed((): Array<{ label: string; value: number }> => {
	return folderCollectionsStore.folderCollectionsSorted
		.filter(collection => collection.activeVersionId !== null)
		.map(collection => ({
			label: collection.title,
			value: collection.id,
		}))
})

const selectedDefaultModerationTimeString = ref<string>('')
const selectedFolderCollection = ref<{ label: string; value: number } | null>(null)

onMounted(async () => {
	await Promise.all([
		folderCollectionsStore.initialize(),
		appSettingsStore.initialize(),
	])

	selectedDefaultModerationTimeString.value = appSettingsStore.defaultModerationTime !== null
		? formatDurationHHMMSS(appSettingsStore.defaultModerationTime)
		: ''
	selectedFolderCollection.value = (() => {
		const id = appSettingsStore.defaultFolderCollectionId
		if (id === null) return null
		return activeFolderCollectionOptions.value.find(option => option.value === id) ?? null
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
	} catch (e) {
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
</script>

<style lang="scss" scoped>
.save-button-container {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
	padding-top: 16px;
}
</style>
