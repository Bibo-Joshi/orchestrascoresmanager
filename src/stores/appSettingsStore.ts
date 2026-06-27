import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client.ts'

/**
 * App-wide user settings store.
 */
export const useAppSettingsStore = defineStore('appSettings', () => {
	const defaultModerationTime = ref<number | null>(null)
	const defaultFolderCollectionId = ref<number | null>(null)
	const isLoading = ref(false)

	/**
	 * Placeholder for backend settings fetch.
	 */
	async function fetchSettingsPlaceholder() {
		isLoading.value = true
		try {
			const response = await apiClients.default.userSettingsGetSetlistSettings()
			const payload = response.data.ocs.data
			defaultModerationTime.value = payload.defaultModerationTime
			defaultFolderCollectionId.value = payload.defaultFolderCollectionId
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Placeholder for backend settings update.
	 * @param moderationTime - New default moderation time in minutes, or null to unset.
	 * @param folderCollectionId - New default folder collection ID, or null to unset.
	 */
	async function updateSettingsPlaceholder(moderationTime: number | null, folderCollectionId: number | null) {
		await apiClients.default.userSettingsPutSetlistSettings({
			defaultModerationTime: moderationTime,
			defaultFolderCollectionId: folderCollectionId,
		})
	}

	/**
	 * Initializes the app settings.
	 */
	async function initialize() {
		await fetchSettingsPlaceholder()
	}

	return {
		defaultModerationTime,
		defaultFolderCollectionId,
		isLoading,
		updateSettingsPlaceholder,
		initialize,
	}
})
