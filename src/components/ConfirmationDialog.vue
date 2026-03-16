<template>
	<NcDialog
		:name="props.title"
		:message="props.message"
		:open="props.open"
		@update:open="handleClose">
		<template #actions>
			<NcButton @click="handleClose">
				<template #icon>
					<CancelIcon :size="20" />
				</template>
				{{ t('Cancel') }}
			</NcButton>
			<NcButton
				variant="warning"
				:disabled="!confirmEnabled"
				class="confirm-button"
				@click="handleConfirm">
				<template #icon>
					<ConfirmIcon :size="20" />
				</template>
				<div class="button-content">
					<NcProgressBar
						v-if="!confirmEnabled"
						:value="progressValue"
						size="small"
						type="circular"
						class="button-progress" />
					<span class="button-text">
						{{ confirmEnabled ? t('Confirm') : `${remainingSeconds}s` }}
					</span>
				</div>
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { t } from '@/utils/l10n.ts'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import { ConfirmIcon, CancelIcon } from '@/icons/vue-material'

/**
 * Countdown duration types
 * - 'short': 3 seconds countdown
 * - 'long': 5 seconds countdown
 * - null: no countdown, confirm button is immediately enabled
 */
type CountdownDuration = 'short' | 'long' | null

interface Props {
	title: string
	message: string
	open?: boolean
	/**
	 * Optional countdown duration before the confirm button is enabled
	 * - 'short': 3 seconds
	 * - 'long': 5 seconds (default)
	 * - null: no countdown
	 */
	countdown?: CountdownDuration
}

interface Emits {
	(e: 'close', confirmed: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
	open: true,
	countdown: 'long',
})

const emit = defineEmits<Emits>()

const COUNTDOWN_SHORT = 3000 // 3 seconds in milliseconds
const COUNTDOWN_LONG = 5000 // 5 seconds in milliseconds

const remainingMilliseconds = ref(0)
const confirmEnabled = ref(false)
let countdownInterval: ReturnType<typeof setInterval> | null = null

/**
 * Get countdown duration in milliseconds based on the countdown prop
 */
function getCountdownDuration(): number {
	if (props.countdown === 'short') {
		return COUNTDOWN_SHORT
	} else if (props.countdown === 'long') {
		return COUNTDOWN_LONG
	}
	return 0 // null means no countdown
}

const progressValue = computed(() => {
	const duration = getCountdownDuration()
	if (duration === 0) return 100
	return ((duration - remainingMilliseconds.value) / duration) * 100
})
const remainingSeconds = computed(() => {
	return Math.ceil(remainingMilliseconds.value / 1000)
})

function startCountdown() {
	const duration = getCountdownDuration()

	// If no countdown, enable confirm button immediately
	if (duration === 0) {
		confirmEnabled.value = true
		return
	}

	remainingMilliseconds.value = duration
	const counterStep = 100 // in milliseconds
	confirmEnabled.value = false

	countdownInterval = setInterval(() => {
		remainingMilliseconds.value -= counterStep

		if (remainingSeconds.value <= 0) {
			confirmEnabled.value = true
			if (countdownInterval) {
				clearInterval(countdownInterval)
				countdownInterval = null
			}
		}
	}, counterStep)
}

function handleConfirm() {
	emit('close', true)
}

function handleClose() {
	emit('close', false)
}

onMounted(() => {
	startCountdown()
})

onUnmounted(() => {
	if (countdownInterval) {
		clearInterval(countdownInterval)
	}
})
</script>

<style lang="scss" scoped>
.confirm-button {
	.button-content {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: center;
		gap: 4px;
		min-width: 5em;
	}

	.button-progress {
		width: 100%;
		max-width: 60px;
	}

	.button-text {
		text-align: center;
	}
}
</style>
