<template>
	<div v-if="!isMobile" class="header-search" :style="{ width: isDesktop ? '60ex' : '40ex' }">
		<NcTextField
			v-model="searchText"
			trailingButtonIcon="close"
			:placeholder="t('Search Score or Score details')"
			:showTrailingButton="!!searchText"
			@trailingButtonClick="searchText = undefined">
			<template #icon>
				<SearchIcon :size="20" />
			</template>
		</NcTextField>
	</div>
	<NcActions
		v-else
		:ariaLabel="t('Search')"
		:size="buttonSize"
		:primary="true"
		variant="primary">
		<template #icon>
			<SearchIcon :size="20" />
		</template>
		<NcActionInput
			v-model="searchText"
			type="string"
			:label="t('Search')"
			trailingButtonIcon="close"
			:showTrailingButton="!!searchText"
			@trailingButtonClick="searchText = undefined">
			<template #icon>
				<SearchIcon :size="20" />
			</template>
			{{ t('Search Score or Score details') }}
		</NcActionInput>
	</NcActions>
</template>

<script setup lang="ts">
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { SearchIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'

/**
 * Search field for the header actions. Rendered as text field on desktop and
 * tablet and as a search icon opening a pop-up input on mobile.
 */
const searchText = defineModel<string | undefined>({ default: undefined })

const { isMobile, isDesktop, buttonSize } = useBreakpoints()
</script>
