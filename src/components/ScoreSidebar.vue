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
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import EntityFolderCollectionsList from '@/components/EntityFolderCollectionsList.vue'
import CommentsList from '@/pages/scores/components/CommentsList.vue'
import ScoreBookInfo from '@/pages/scores/components/ScoreBookInfo.vue'
import { CommentIcon, FolderCollectionIcon, ScoreBookIcon } from '@/icons/vue-material'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { t } from '@/utils/l10n.ts'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoreSidebarStore = useScoreSidebarStore()
</script>
