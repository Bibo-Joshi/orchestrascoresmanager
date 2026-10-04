<template>
	<NcListItem
		:name="setlist.title"
		:details="formattedStartDateTime"
		:to="{ name: 'setlist', params: { id: setlist.id } }">
		<template v-if="setlist.description" #subname>
			<span class="subname-truncated">{{ setlist.description }}</span>
		</template>
		<template v-if="setlist.isDraft" #indicator>
			<PencilCircleIcon :size="20" :title="t('Draft')" />
		</template>
		<template v-if="editable" #actions>
			<NcActionButton @click.stop="showCloneDialog = true">
				<template #icon>
					<CloneIcon :size="20" />
				</template>
				{{ t('Clone') }}
			</NcActionButton>
			<NcActionButton @click.stop="showDeleteDialog = true">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('Delete') }}
			</NcActionButton>
		</template>
	</NcListItem>

	<!-- Clone dialog -->
	<CloneSetlistDialog
		v-if="showCloneDialog"
		:isOpen="showCloneDialog"
		:setlistId="setlist.id"
		@update:isOpen="showCloneDialog = $event" />

	<!-- Delete confirmation dialog -->
	<ConfirmationDialog
		v-if="showDeleteDialog"
		:title="t('Delete setlist')"
		:message="t('Are you sure you want to delete this setlist?')"
		:open="showDeleteDialog"
		@close="handleDeleteConfirmation" />
</template>

<script setup lang="ts">
import type { Setlist } from '@/api/generated/openapi/data-contracts'

import { showSuccess } from '@nextcloud/dialogs'
import { computed, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import CloneSetlistDialog from '@/components/CloneSetlistDialog.vue'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { formatDateTimeStr } from '@/composables/useDateFormatting'
import { CloneIcon, DeleteIcon, PencilCircleIcon } from '@/icons/vue-material'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

interface Props {
	setlist: Setlist
	editable: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<Emits>()

interface Emits {
	(e: 'delete', id: number): void
}

const setlistsStore = useSetlistsStore()
const showDeleteDialog = ref(false)
const showCloneDialog = ref(false)

/**
 * Format the start date/time for display
 */
const formattedStartDateTime = computed(() => {
	if (!props.setlist.startDateTime) {
		return ''
	}
	return formatDateTimeStr(props.setlist.startDateTime)
})

/**
 * Handle delete confirmation dialog response
 *
 * @param confirmed - Whether the deletion was confirmed
 */
async function handleDeleteConfirmation(confirmed: boolean) {
	showDeleteDialog.value = false

	if (!confirmed) {
		return
	}

	await tryShowError(
		async () => {
			await setlistsStore.deleteSetlist(props.setlist.id)
			showSuccess(t('Setlist deleted'))
			emit('delete', props.setlist.id)
		},
		t('Deleting setlist failed: '),
	)
}
</script>

<style lang="scss" scoped>
.subname-truncated {
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
