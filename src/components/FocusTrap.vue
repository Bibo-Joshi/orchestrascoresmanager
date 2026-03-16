<template>
	<button type="button" class="sr-only" aria-hidden="true" />
</template>

<style scoped>
/* Visually hide element but keep it focusable for keyboard/dialog focus */
.sr-only {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip-path: inset(100%) !important;
	white-space: nowrap !important;
	border: 0 !important;
	background: transparent !important;
}
</style>
