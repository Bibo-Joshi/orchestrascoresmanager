<template>
	<NcButton
		:size="buttonSize"
		variant="primary"
		:text="buttonText(t('Share'))"
		@click="openDialog">
		<template #icon>
			<ShareIcon :size="20" />
		</template>
	</NcButton>

	<NcDialog
		:name="t('Share setlist')"
		:open="dialogOpen"
		size="normal"
		@update:open="onOpenChange">
		<div class="share-dialog">
			<NcLoadingIcon v-if="isGenerating" :size="48" />
			<template v-else-if="imageUrl">
				<p>{{ t('Review the image and press "Share" to send it to a messenger.') }}</p>
				<img class="share-dialog__image" :src="imageUrl" :alt="t('Setlist image')">
			</template>
		</div>
		<template #actions>
			<NcButton @click="closeDialog">
				<template #icon>
					<CancelIcon />
				</template>
				{{ t('Close') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="!file || !model || isSharing"
				@click="onShare">
				<template #icon>
					<LoadingIcon v-if="isSharing" />
					<ShareIcon v-else />
				</template>
				{{ t('Share') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import type { SetlistShareModel } from '../utils/setlistShareModel.ts'
import type { FolderCollection, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'

import { onBeforeUnmount, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useSetlistShare } from '../composables/useSetlistShare.ts'
import { buildSetlistShareModel } from '../utils/setlistShareModel.ts'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { CancelIcon, LoadingIcon, ShareIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

interface Props {
	setlist: Setlist
	entries: SetlistEntry[]
	fcvScoresMap: Map<number, number>
	fcvScoreBookIndicesMap: Map<number, number>
	folderCollection: FolderCollection | null
}

const props = defineProps<Props>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const { buttonSize, buttonText } = useBreakpoints()
const { isGenerating, generate, share } = useSetlistShare()

const dialogOpen = ref(false)
const isSharing = ref(false)
const model = ref<SetlistShareModel | null>(null)
const file = ref<File | null>(null)
const imageUrl = ref<string | null>(null)

/**
 * Release the generated image and its object URL.
 */
function reset(): void {
	if (imageUrl.value) {
		URL.revokeObjectURL(imageUrl.value)
	}
	imageUrl.value = null
	file.value = null
	model.value = null
}

/**
 * Open the dialog and generate the share image in the background.
 */
async function openDialog(): Promise<void> {
	reset()
	dialogOpen.value = true
	await tryShowError(
		async () => {
			const newModel = buildSetlistShareModel({
				setlist: props.setlist,
				entries: props.entries,
				getScoreById: (id) => scoresStore.getScoreById(id),
				getScoreBookById: (id) => scoreBooksStore.getScoreBookById(id),
				fcvScoresMap: props.fcvScoresMap,
				fcvScoreBookIndicesMap: props.fcvScoreBookIndicesMap,
				folderCollection: props.folderCollection,
				url: window.location.href,
			})
			const newFile = await generate(newModel)
			if (!dialogOpen.value) {
				return
			}
			model.value = newModel
			file.value = newFile
			imageUrl.value = URL.createObjectURL(newFile)
		},
		t('Sharing failed: '),
		() => {
			dialogOpen.value = false
		},
	)
}

/**
 * Close the dialog and release the image.
 */
function closeDialog(): void {
	dialogOpen.value = false
	reset()
}

/**
 * Handle open-state changes emitted by the dialog.
 *
 * @param open - New open state
 */
function onOpenChange(open: boolean): void {
	if (!open) {
		closeDialog()
	}
}

/**
 * Share the generated image. Called directly from the click so that the
 * browser still sees the user gesture.
 */
async function onShare(): Promise<void> {
	if (!model.value || !file.value) {
		return
	}
	isSharing.value = true
	await tryShowError(
		() => share(model.value as SetlistShareModel, file.value as File),
		t('Sharing failed: '),
	)
	isSharing.value = false
}

onBeforeUnmount(reset)
</script>

<style lang="scss" scoped>
.share-dialog {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	min-height: 120px;

	&__image {
		max-width: 100%;
		max-height: 60vh;
		border: 1px solid var(--color-border);
	}
}
</style>
