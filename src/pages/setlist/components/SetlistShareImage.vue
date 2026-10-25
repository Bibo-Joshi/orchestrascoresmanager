<template>
	<div class="share-image">
		<div v-if="model.isDraft" class="watermark" aria-hidden="true">
			<span v-for="i in WATERMARK_COUNT" :key="i">{{ draftLabel }}</span>
		</div>
		<h1>{{ model.title }}</h1>
		<p v-for="line in model.subtitleLines" :key="line" class="subtitle">
			{{ line }}
		</p>
		<table>
			<thead>
				<tr>
					<th v-for="column in model.columns" :key="column.id">
						{{ column.label }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(row, rowIndex) in model.rows" :key="rowIndex">
					<td v-for="(cell, cellIndex) in row" :key="cellIndex">
						{{ cell }}
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup lang="ts">
import type { SetlistShareModel } from '../utils/setlistShareModel.ts'

import { t } from '@/utils/l10n'

interface Props {
	model: SetlistShareModel
}

defineProps<Props>()

const WATERMARK_COUNT = 60
const draftLabel = t('Draft').toUpperCase()
</script>

<style lang="scss" scoped>
.share-image {
	position: relative;
	overflow: hidden;
	box-sizing: border-box;
	width: max-content;
	min-width: 800px;
	padding: 40px;
	background: #fff;
	color: #000;
	font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;

	h1 {
		margin: 0 0 8px;
		font-size: 36px;
		line-height: 1.2;
	}

	.subtitle {
		margin: 0 0 4px;
		color: #555;
		font-size: 20px;
	}

	table {
		width: 100%;
		margin-top: 24px;
		border-collapse: collapse;
		font-size: 22px;
	}

	th {
		text-align: left;
		border-bottom: 3px solid #000;
	}

	th, td {
		padding: 8px 10px;
		vertical-align: top;
	}

	tbody tr:nth-child(even) {
		background: #f3f3f3;
	}

	.watermark {
		position: absolute;
		inset: -50%;
		z-index: 1;
		display: flex;
		flex-wrap: wrap;
		align-content: space-around;
		justify-content: space-around;
		transform: rotate(-35deg);
		pointer-events: none;

		span {
			padding: 30px 40px;
			color: transparent;
			font-size: 90px;
			font-weight: bold;
			-webkit-text-stroke: 2px rgba(0, 0, 0, 0.12);
		}
	}
}
</style>
