<template>
	<NcAppSidebar
		v-if="scoreBookSidebarStore.isOpen && scoreBookSidebarStore.selectedScoreBook"
		v-model="scoreBookSidebarStore.isOpen"
		:name="scoreBookSidebarStore.selectedScoreBook.title"
		:force-tabs="true"
		@close="scoreBookSidebarStore.closeSidebar()">
		<NcAppSidebarTab
			id="scores"
			:name="t('Scores')">
			<template #icon>
				<ScoreIcon :size="20" />
			</template>
			<ScoreBookScoresList :score-book-id="scoreBookSidebarStore.selectedScoreBook.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="foldercollections"
			:name="t('Folder Collections')">
			<template #icon>
				<FolderCollectionIcon :size="20" />
			</template>
			<EntityFolderCollectionsList type="scorebook" :entity-id="scoreBookSidebarStore.selectedScoreBook.id" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n.ts'
import { ScoreIcon, FolderCollectionIcon } from '@/icons/vue-material'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import { useScoreBookSidebarStore } from '@/stores/scoreBookSidebarStore'
import ScoreBookScoresList from './ScoreBookScoresList.vue'
import EntityFolderCollectionsList from '@/components/EntityFolderCollectionsList.vue'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoreBookSidebarStore = useScoreBookSidebarStore()
</script>
