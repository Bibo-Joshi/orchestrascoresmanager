<template>
	<div class="audio-item">
		<component :is="icon" :size="24" class="audio-icon" />
		<div class="audio-body">
			<a
				v-if="url !== null"
				:href="url"
				target="_blank"
				rel="noopener noreferrer"
				class="audio-title">
				{{ title }}
				<OpenExternalIcon :size="14" />
			</a>
			<span v-else class="audio-title">{{ title }}</span>
			<p v-if="audio.comment" class="audio-comment">
				{{ audio.comment }}
			</p>
		</div>
		<NcActions>
			<NcActionButton
				:aria-label="t('Delete audio example')"
				:disabled="isDeleting"
				@click="handleDeleteClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('Delete') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script setup lang="ts">
import type { ScoreAudio } from '@/api/generated/openapi/data-contracts'

import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { DeleteIcon, OpenExternalIcon } from '@/icons/vue-material'
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
			countdown: 'short',
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

<style lang="scss" scoped>
.audio-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 0;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}
}

.audio-icon {
	flex-shrink: 0;
}

.audio-body {
	flex: 1;
	min-width: 0;
}

.audio-title {
	font-weight: bold;

	.material-design-icon {
		vertical-align: middle;
		display: inline-block;
	}
}

.audio-comment {
	margin: 0;
	color: var(--color-text-maxcontrast);
	overflow-wrap: break-word;
}
</style>
