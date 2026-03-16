<template>
	<NcAppNavigation
		:aria-label="t('Main app navigation')">
		<template #list>
			<!-- Static navigation items -->
			<NcAppNavigationItem
				v-for="item in staticNavigationItems"
				:key="item.name"
				:name="item.displayName"
				:to="{ path: item.path }">
				<template #icon>
					<component :is="item.icon" v-if="item.icon" :size="20" />
				</template>
			</NcAppNavigationItem>

			<!-- Collapsible Folder Collections -->
			<NcAppNavigationItem
				v-if="folderCollectionsNavigationItem"
				:key="folderCollectionsNavigationItem.name"
				:name="folderCollectionsNavigationItem.displayName"
				:to="{ path: folderCollectionsNavigationItem.path }"
				allow-collapse
				:open="isFolderCollectionsOpen">
				<template #icon>
					<component :is="folderCollectionsNavigationItem.icon" v-if="folderCollectionsNavigationItem.icon" :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble :count="folderCollectionsStore.folderCollections.length" />
				</template>
				<ul>
					<NcAppNavigationItem
						v-for="fc in folderCollectionsStore.folderCollectionsSorted"
						:key="`fc-${fc.id}`"
						:name="fc.title"
						:to="{ name: 'foldercollection', params: { id: fc.id } }">
						<template #counter>
							<NcCounterBubble v-if="fc.scoreCount" :count="fc.scoreCount" />
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem
						v-if="folderCollectionsStore.folderCollections.length === 0"
						:name="t('No folder collections yet')" />
				</ul>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import { navigation } from '@/navigation'
import { NavigationItem } from '@/types/navigation'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { t } from '@/utils/l10n'

const route = useRoute()
const folderCollectionsStore = useFolderCollectionsStore()

// Initialize folder collections store when navigation mounts
onMounted(() => {
	folderCollectionsStore.initialize()
})

/**
 * Static navigation items (non-folder collection items)
 */
const staticNavigationItems = computed((): NavigationItem[] => {
	return navigation.filter(item => item.name !== 'foldercollections')
})

/**
 * Folder collections navigation item (for the parent collapsible item)
 */
const folderCollectionsNavigationItem = computed((): NavigationItem | undefined => {
	return navigation.find(item => item.name === 'foldercollections')
})

/**
 * Determine if the folder collections navigation should be expanded
 * Expanded only when viewing a specific folder collection detail page
 */
const isFolderCollectionsOpen = computed((): boolean => {
	return route.name === 'foldercollection'
})
</script>
