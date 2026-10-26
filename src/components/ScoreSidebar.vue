<template>
	<NcAppSidebar
		v-if="scoreSidebarStore.isOpen && scoreSidebarStore.selectedScore"
		v-model="scoreSidebarStore.isOpen"
		:name="scoreSidebarStore.selectedScore.title"
		:forceTabs="true"
		@close="scoreSidebarStore.closeSidebar()">
		<NcAppSidebarTab
			v-if="editable"
			id="comments"
			:name="t('Comments')">
			<template #icon>
				<CommentIcon :size="20" />
			</template>
			<CommentsList :scoreId="scoreSidebarStore.selectedScore.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="foldercollections"
			:name="t('Folder Collections')">
			<template #icon>
				<FolderCollectionIcon :size="20" />
			</template>
			<EntityFolderCollectionsList type="score" :entityId="scoreSidebarStore.selectedScore.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="audioexamples"
			:name="t('Audio Examples')">
			<template #icon>
				<AudioIcon :size="20" />
			</template>
			<AudioExamplesList :scoreId="scoreSidebarStore.selectedScore.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			v-if="scoreSetlists.total.value > 0 || (!scoreSetlists.isLoading.value && !scoreSetlists.isLoaded.value)"
			id="setlists"
			:name="t('Setlists')">
			<template #icon>
				<SetlistIcon :size="20" />
			</template>
			<ScoreSetlistsList
				:setlists="scoreSetlists.setlists.value"
				:total="scoreSetlists.total.value"
				:isLoading="scoreSetlists.isLoading.value"
				:isLoaded="scoreSetlists.isLoaded.value"
				:isLoadingMore="scoreSetlists.isLoadingMore.value"
				:hasMore="scoreSetlists.nextCursor.value !== null"
				@loadMore="scoreSetlists.loadMore(scoreSidebarStore.selectedScore.id)" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			v-if="scoreSidebarStore.selectedScore.scoreBook !== null"
			id="scorebook"
			:name="t('Score Book')">
			<template #icon>
				<ScoreBookIcon :size="20" />
			</template>
			<ScoreBookInfo
				:scoreId="scoreSidebarStore.selectedScore.id"
				:scoreBookId="scoreSidebarStore.selectedScore.scoreBook!.id" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import EntityFolderCollectionsList from '@/components/EntityFolderCollectionsList.vue'
import AudioExamplesList from '@/pages/scores/components/AudioExamplesList.vue'
import CommentsList from '@/pages/scores/components/CommentsList.vue'
import ScoreBookInfo from '@/pages/scores/components/ScoreBookInfo.vue'
import ScoreSetlistsList from '@/pages/scores/components/ScoreSetlistsList.vue'
import { useScoreSetlists } from '@/composables/useScoreSetlists'
import { AudioIcon, CommentIcon, FolderCollectionIcon, ScoreBookIcon, SetlistIcon } from '@/icons/vue-material'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { t } from '@/utils/l10n.ts'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoreSidebarStore = useScoreSidebarStore()
const scoreSetlists = useScoreSetlists()

// Load the setlist history on demand when the sidebar is opened for a score
watch(
	() => [scoreSidebarStore.isOpen, scoreSidebarStore.selectedScore?.id] as const,
	([isOpen, scoreId]) => {
		if (isOpen && scoreId !== undefined) {
			scoreSetlists.load(scoreId)
		}
	},
	{ immediate: true },
)
</script>
