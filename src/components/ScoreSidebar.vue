<template>
	<NcAppSidebar
		v-if="scoreSidebarStore.isOpen && scoreSidebarStore.selectedScore"
		v-model="scoreSidebarStore.isOpen"
		:name="scoreSidebarStore.selectedScore.title"
		:force-tabs="true"
		@close="scoreSidebarStore.closeSidebar()">
		<NcAppSidebarTab
			v-if="editable"
			id="comments"
			:name="t('Comments')">
			<template #icon>
				<CommentIcon :size="20" />
			</template>
			<CommentsList :score-id="scoreSidebarStore.selectedScore.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="foldercollections"
			:name="t('Folder Collections')">
			<template #icon>
				<FolderCollectionIcon :size="20" />
			</template>
			<EntityFolderCollectionsList type="score" :entity-id="scoreSidebarStore.selectedScore.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			v-if="scoreSidebarStore.selectedScore.scoreBook !== null"
			id="scorebook"
			:name="t('Score Book')">
			<template #icon>
				<ScoreBookIcon :size="20" />
			</template>
			<ScoreBookInfo
				:score-id="scoreSidebarStore.selectedScore.id"
				:score-book-id="scoreSidebarStore.selectedScore.scoreBook!.id" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n.ts'
import { CommentIcon, FolderCollectionIcon, ScoreBookIcon } from '@/icons/vue-material'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import CommentsList from '@/pages/scores/components/CommentsList.vue'
import EntityFolderCollectionsList from '@/components/EntityFolderCollectionsList.vue'
import ScoreBookInfo from '@/pages/scores/components/ScoreBookInfo.vue'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoreSidebarStore = useScoreSidebarStore()
</script>
