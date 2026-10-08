<template>
	<NcAppSidebar
		v-if="scoreBookSidebarStore.isOpen && scoreBookSidebarStore.selectedScoreBook"
		v-model="scoreBookSidebarStore.isOpen"
		:name="scoreBookSidebarStore.selectedScoreBook.title"
		:forceTabs="true"
		@close="scoreBookSidebarStore.closeSidebar()">
		<NcAppSidebarTab
			id="scores"
			:name="t('Scores')">
			<template #icon>
				<ScoreIcon :size="20" />
			</template>
			<ScoreBookScoresList :scoreBookId="scoreBookSidebarStore.selectedScoreBook.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="foldercollections"
			:name="t('Folder Collections')">
			<template #icon>
				<FolderCollectionIcon :size="20" />
			</template>
			<EntityFolderCollectionsList type="scorebook" :entityId="scoreBookSidebarStore.selectedScoreBook.id" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import ScoreBookScoresList from './ScoreBookScoresList.vue'
import EntityFolderCollectionsList from '@/components/EntityFolderCollectionsList.vue'
import { FolderCollectionIcon, ScoreIcon } from '@/icons/vue-material'
import { useScoreBookSidebarStore } from '@/stores/scoreBookSidebarStore'
import { t } from '@/utils/l10n.ts'

const scoreBookSidebarStore = useScoreBookSidebarStore()
</script>
