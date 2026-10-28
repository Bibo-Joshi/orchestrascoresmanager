<template>
	<NcListItem
		:name="title"
		:href="url ?? undefined"
		target="_blank"
		:bold="false"
		forceDisplayActions>
		<template #icon>
			<component :is="icon" :size="24" />
		</template>
		<template v-if="audio.comment" #subname>
			{{ audio.comment }}
		</template>
		<template #actions>
			<NcActionButton
				:disabled="isDeleting"
				@click="handleDeleteClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('Delete') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script setup lang="ts">
import type { ScoreAudio } from '@/api/generated/openapi/data-contracts'

import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { DeleteIcon } from '@/icons/vue-material'
import { useScoreAudiosStore } from '@/stores/scoreAudiosStore'
import { getAudioIcon, getAudioTitle, getAudioUrl } from '@/utils/audioProviders'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n.ts'

interface Props {
	audio: ScoreAudio
}

const props = defineProps<Props>()

const scoreAudiosStore = useScoreAudiosStore()
const isDeleting = ref(false)

const icon = computed(() => getAudioIcon(props.audio))
const title = computed(() => getAudioTitle(props.audio))
const url = computed(() => getAudioUrl(props.audio))

async function handleDeleteClick() {
	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Delete Audio Example'),
			message: t('Are you sure you want to delete this audio example? This action cannot be undone.'),
			countdown: null,
		},
	)

	if (!result || isDeleting.value) {
		return
	}

	isDeleting.value = true
	await tryShowError(
		async () => await scoreAudiosStore.deleteAudio(props.audio.id),
		t('Failed to delete audio example: '),
	)
	isDeleting.value = false
}
</script>
