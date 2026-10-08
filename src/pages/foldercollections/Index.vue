<template>
	<Layout :title="t('Folder Collections')">
		<template #content>
			<ContentStateWrapper
				:loading="folderCollectionsStore.isLoading"
				:isEmpty="folderCollections.length === 0"
				:emptyText="t('No folder collections yet')"
				:emptyDescription="t('Create your first folder collection to organize your scores')">
				<template #emptyIcon>
					<FolderCollectionIcon :size="64" />
				</template>
				<ul class="folder-collections-list">
					<NcListItem
						v-for="fc in folderCollections"
						:key="fc.id"
						:name="fc.title"
						:counterNumber="fc.scoreCount || 0"
						:details="fc.collectionType === 'indexed' ? t('Indexed') : t('Alphabetical')"
						:to="{ name: 'foldercollection', params: { id: fc.id } }"
						:bold="false">
						<template #subname>
							{{ fc.description || '' }}
						</template>
						<template v-if="editable" #actions>
							<NcActionButton
								:aria-label="t('Delete folder collection')"
								@click="handleDeleteClick(fc)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('Delete') }}
							</NcActionButton>
						</template>
					</NcListItem>
				</ul>
			</ContentStateWrapper>
		</template>

		<template #headerActions>
			<AddFolderCollectionButton :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import type { FolderCollection } from '@/api/generated/openapi/data-contracts'

import { loadState } from '@nextcloud/initial-state'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, onMounted } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import AddFolderCollectionButton from './components/AddFolderCollectionButton.vue'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import Layout from '@/components/Layout.vue'
import { DeleteIcon, FolderCollectionIcon } from '@/icons/vue-material'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

const folderCollectionsStore = useFolderCollectionsStore()

// Load initial state from the server
const editable = !!loadState('orchestrascoresmanager', 'editable')

// Use computed to maintain reactivity with the store
const folderCollections = computed(() => folderCollectionsStore.folderCollections)

// Initialize the store on mount
onMounted(() => {
	folderCollectionsStore.initialize()
})

async function handleDeleteClick(fc: FolderCollection) {
	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Delete Folder Collection'),
			message: t('Are you sure you want to delete this folder collection? This action cannot be undone.'),
		},
	)

	if (result) {
		await tryShowError(
			async () => await folderCollectionsStore.deleteFolderCollection(fc.id),
			t('Failed to delete folder collection: '),
		)
	}
}
</script>

<style lang="scss" scoped>
.folder-collections-list {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}
</style>
