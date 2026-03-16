<template>
	<NcDialog
		:name="t('Start new version')"
		:open="props.open"
		@update:open="handleClose">
		<p>{{ t('This will create a new version starting on the selected date. The current active version will be marked as ended one day before. All scores and score books will be copied to the new version.') }}</p>

		<NcDateTimePickerNative
			v-model="selectedDate"
			:label="t('Start date for new version')"
			type="date"
			:min="minDate" />
		<template #actions>
			<NcButton @click="handleClose">
				<template #icon>
					<CancelIcon :size="20" />
				</template>
				{{ t('Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="!isValidDate"
				@click="handleConfirm">
				<template #icon>
					<ConfirmIcon :size="20" />
				</template>
				{{ t('Start Version') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { t } from '@/utils/l10n.ts'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import { ConfirmIcon, CancelIcon } from '@/icons/vue-material'

interface Props {
	/**
	 * The start date of the current active version (Y-m-d format)
	 */
	activeVersionValidFrom: string
	open?: boolean
}

interface Emits {
	(e: 'close', result: { confirmed: boolean; validFrom?: string }): void
}

const props = withDefaults(defineProps<Props>(), {
	open: true,
})

const emit = defineEmits<Emits>()

const selectedDate = ref<Date | null>(null)

/**
 * Calculate minimum date (active version's validFrom + 1 day)
 */
const minDate = computed(() => {
	const activeDate = new Date(props.activeVersionValidFrom)
	// Add 1 day
	const minDate = new Date(activeDate)
	minDate.setDate(minDate.getDate() + 1)
	return minDate
})

/**
 * Check if the selected date is valid
 */
const isValidDate = computed(() => {
	if (!selectedDate.value) return false
	return selectedDate.value >= minDate.value
})

/**
 * Handle confirm button click
 */
function handleConfirm() {
	if (selectedDate.value) {
		// Format date as Y-m-d using ISO string
		const validFrom = selectedDate.value.toISOString().split('T')[0]

		emit('close', { confirmed: true, validFrom })
	}
}

/**
 * Handle dialog close
 */
function handleClose() {
	emit('close', { confirmed: false })
}

/**
 * Initialize with minimum date
 */
onMounted(() => {
	selectedDate.value = minDate.value
})
</script>
