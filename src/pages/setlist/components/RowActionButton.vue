<template>
	<div class="row-action-button-container" :style="props.params.showDeleteButton ? '' : 'position: absolute'">
		<NcActions>
			<NcActionButton
				v-if="!!props.params.score"
				:close-after-click="true"
				:name="t('Details')"
				@click="handleInfoButton">
				<template #icon>
					<InfoIcon :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton
				v-if="props.params.showDeleteButton == true"
				:close-after-click="true"
				:name="t('Delete')"
				@click="handleDeleteButton">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script setup lang="ts">
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { InfoIcon, DeleteIcon } from '@/icons/vue-material'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { useSetlistEntriesStore } from '@/stores/setlistEntriesStore'
import type { Score, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { t } from '@/utils/l10n'
import { tryShowError } from '@/utils/errorHandling'

type Params = {
	score?: Score
	setlistEntry: SetlistEntry
	showDeleteButton?: boolean
}

interface Props {
	params: Params
}

const props = defineProps<Props>()
const scoreSidebarStore = useScoreSidebarStore()
const setlistEntriesStore = useSetlistEntriesStore()

function handleInfoButton() {
	const p = props.params
	if (!p.score) {
		return
	}

	scoreSidebarStore.openSidebar(p.score)
}

async function handleDeleteButton() {
	const entry = props.params.setlistEntry

	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Delete Setlist Entry'),
			message: t('Are you sure you want to delete this setlist entry?'),
			countdown: null,
		},
	)

	if (!result) {
		return
	}

	await tryShowError(
		async () => await setlistEntriesStore.deleteEntry(entry.id, entry.setlistId),
		t('Failed to delete setlist entry: '),
	)
}
</script>

<style lang="scss" scoped>
.row-action-button-container {
	display: flex;
	align-items: center;
	height: 100%;
	width: 100%;
}
</style>
