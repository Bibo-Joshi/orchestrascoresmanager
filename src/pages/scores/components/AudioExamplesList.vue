<template>
	<ContentStateWrapper
		:loading="scoreAudiosStore.isLoading"
		:error="scoreAudiosStore.loadError"
		:isEmpty="scoreAudiosStore.audios.length === 0"
		:errorText="t('Failed to fetch audio examples. Please reload the page.')"
		:emptyText="t('No audio examples yet')"
		:showAboveContentOnEmpty="true">
		<template #emptyIcon>
			<AudioIcon />
		</template>
		<template #aboveContent>
			<form class="new-audio-container" @submit.prevent="submitAudio">
				<NcTextField
					v-model="newUrl"
					:label="t('URL')"
					:placeholder="t('Link to an audio example …')"
					:error="newUrl.trim() !== '' && !isUrlValid"
					:helperText="urlHelperText"
					type="url" />
				<NcTextField
					v-model="newComment"
					:label="t('Comment (optional)')" />
				<div class="new-audio-actions">
					<NcButton
						:disabled="submitting || !isUrlValid"
						variant="primary"
						:aria-label="t('Add audio example')"
						type="submit">
						<template #icon>
							<NcLoadingIcon v-if="submitting" :size="20" />
							<ConfirmIcon v-else :size="20" />
						</template>
					</NcButton>
				</div>
			</form>
		</template>
		<template #default>
			<ul>
				<AudioExampleItem
					v-for="audio in scoreAudiosStore.audios"
					:key="audio.id"
					:audio="audio" />
			</ul>
		</template>
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AudioExampleItem from './AudioExampleItem.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { AudioIcon, ConfirmIcon } from '@/icons/vue-material'
import { useScoreAudiosStore } from '@/stores/scoreAudiosStore'
import { getAudioProvider, isValidHttpUrl, parseAudioUrl } from '@/utils/audioProviders'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n.ts'

interface Props {
	scoreId: number
}

const props = defineProps<Props>()

const scoreAudiosStore = useScoreAudiosStore()
const newUrl = ref('')
const newComment = ref('')
const submitting = ref(false)

const isUrlValid = computed(() => isValidHttpUrl(newUrl.value))
const detectedSource = computed(() => parseAudioUrl(newUrl.value))
const urlHelperText = computed(() => {
	if (newUrl.value.trim() !== '' && !isUrlValid.value) {
		return t('Please enter a valid http(s) URL')
	}
	const provider = getAudioProvider(detectedSource.value?.provider ?? null)
	return provider !== null ? t('Detected provider: {provider}', { provider: provider.name }) : ''
})

async function submitAudio() {
	if (!isUrlValid.value || submitting.value) {
		return
	}

	submitting.value = true
	const source = detectedSource.value
	const comment = newComment.value.trim() || null
	await tryShowError(
		async () => {
			await scoreAudiosStore.createAudio(
				props.scoreId,
				{
					audioExample: source !== null
						? { provider: source.provider, providerId: source.providerId }
						: { link: newUrl.value.trim() },
					comment,
				},
			)
			newUrl.value = ''
			newComment.value = ''
		},
		t('Failed to add audio example: '),
	)
	submitting.value = false
}

watch(() => props.scoreId, (scoreId) => {
	newUrl.value = ''
	newComment.value = ''
	scoreAudiosStore.loadForScore(scoreId)
}, { immediate: true })
</script>

<style lang="scss" scoped>
.new-audio-container {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.new-audio-actions {
	display: flex;
	justify-content: flex-end;
}
</style>
