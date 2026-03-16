<template>
	<div>
		<!-- Show ScoreSidebar when a score is selected -->
		<ScoreSidebar v-if="scoreSidebarStore.isOpen" :editable="editable" />
		<!-- Otherwise show SetlistSidebar -->
		<SetlistSidebar v-else :setlist="setlist" :editable="editable" />
	</div>
</template>

<script setup lang="ts">
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import SetlistSidebar from './SetlistSidebar.vue'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import type { Setlist } from '@/api/generated/openapi/data-contracts'

interface Props {
	setlist: Setlist | undefined
	editable: boolean
}

defineProps<Props>()

const scoreSidebarStore = useScoreSidebarStore()
</script>
