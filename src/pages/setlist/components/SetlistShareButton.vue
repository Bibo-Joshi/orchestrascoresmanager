<template>
	<NcButton
		:size="buttonSize"
		variant="primary"
		:text="buttonText(t('Share'))"
		:disabled="isSharing"
		@click="onShare">
		<template #icon>
			<LoadingIcon v-if="isSharing" :size="20" />
			<ShareIcon v-else :size="20" />
		</template>
	</NcButton>
</template>

<script setup lang="ts">
import type { FolderCollection, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'

import NcButton from '@nextcloud/vue/components/NcButton'
import { useSetlistShare } from '../composables/useSetlistShare.ts'
import { buildSetlistShareModel } from '../utils/setlistShareModel.ts'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { LoadingIcon, ShareIcon } from '@/icons/vue-material'
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
const { isSharing, share } = useSetlistShare()

/**
 * Build the share model for the current setlist and share it.
 */
async function onShare(): Promise<void> {
	await tryShowError(
		async () => {
			const model = buildSetlistShareModel({
				setlist: props.setlist,
				entries: props.entries,
				getScoreById: (id) => scoresStore.getScoreById(id),
				getScoreBookById: (id) => scoreBooksStore.getScoreBookById(id),
				fcvScoresMap: props.fcvScoresMap,
				fcvScoreBookIndicesMap: props.fcvScoreBookIndicesMap,
				folderCollection: props.folderCollection,
				url: window.location.href,
			})
			await share(model)
		},
		t('Sharing failed: '),
	)
}
</script>
