<template>
	<NcSettingsSection :name="t('Group Permissions')">
		<div class="group_selection">
			<p class="admin-settings__description">
				{{ t('Select the groups that are allowed to edit the orchestra scores table.') }}
			</p>

			<NcSettingsSelectGroup
				:label="t('Allowed Groups')"
				:model-value="allowedGroups"
				:placeholder="t('Select groups...')"
				:multiple="true"
				:loading="loading"
				@update:model-value="onGroupsChanged" />
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/utils/l10n'
import { isPasswordConfirmationRequired, confirmPassword, PwdConfirmationMode } from '@nextcloud/password-confirmation'
import { showSuccess, showError } from '@nextcloud/dialogs'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'

// Reactive data
const allowedGroups = ref<string[]>(loadState('orchestrascoresmanager', 'allowed_groups'))
const loading = ref(false)

// Handle group selection changes
async function onGroupsChanged(newGroups: string[]) {
	loading.value = true
	try {
		// Check if password confirmation is required
		if (isPasswordConfirmationRequired(PwdConfirmationMode.Lax)) {
			await confirmPassword()
		}

		const response = await apiClients.admin.adminPostEditGroups({ editGroups: newGroups })

		// Update local state
		allowedGroups.value = response.data.ocs.data.editGroups

		// Show success message
		showSuccess(t('Settings updated successfully.'))
	} catch (err) {
		console.error('Failed to update settings:', err)
		showError(t('Failed to update settings.'))
	} finally {
		loading.value = false
	}
}
</script>

<style lang="scss" scoped>
.group_selection {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline)
}
</style>
