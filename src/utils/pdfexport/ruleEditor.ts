/**
 * Convert visual editor rules to and from stored rule expressions.
 */

import type { SetlistTemplate } from '@/api/generated/openapi/data-contracts'

type PdfTemplateRule = SetlistTemplate['rules'][number]
type PdfTemplateCondition = PdfTemplateRule['expression']

export type PdfRuleOperator = '' | 'AND' | 'OR' | 'XOR'
export type PdfRuleKind = 'tags' | 'break'

/** Editor form model: users never have to enter JSON or free-form rule expressions. */
export interface PdfRuleDraft {
	id: string
	label: string
	kind: PdfRuleKind
	tag1: string
	operator: PdfRuleOperator
	tag2: string
	backgroundColor: string | null
	cellPadding: number | null
}

function tagCondition(tag: string) {
	return { fact: 'setlistentry', path: '$.tags', operator: 'contains', value: tag.trim().toLowerCase() }
}

function breakCondition() {
	return { fact: 'setlistentry', path: '$.breakDuration', operator: 'greaterThan', value: 0 }
}

/**
 * Conditions use the JSON Rules Engine AST (all/any/not).
 * @param draft Editor rule definition.
 */
export function buildPdfRuleCondition(draft: PdfRuleDraft): PdfTemplateCondition {
	if (draft.kind === 'break') {
		return { all: [breakCondition()] }
	}

	const first = tagCondition(draft.tag1.trim())
	if (!draft.operator) {
		return { all: [first] }
	}
	const second = tagCondition(draft.tag2.trim())
	switch (draft.operator) {
		case 'AND':
			return { all: [first, second] }
		case 'OR':
			return { any: [first, second] }
		case 'XOR':
			return {
				any: [
					{ all: [first, { not: second }] },
					{ all: [{ not: first }, second] },
				],
			}
	}
}

function isRecord(value: unknown): value is Record<string, unknown> {
	return value !== null && typeof value === 'object' && !Array.isArray(value)
}

function readTag(value: unknown): string | null {
	if (!isRecord(value)) {
		return null
	}
	return value.fact === 'setlistentry'
		&& value.path === '$.tags'
		&& value.operator === 'contains'
		&& typeof value.value === 'string'
		? value.value
		: null
}

function readGroup(value: unknown, kind: 'all' | 'any'): unknown[] {
	return isRecord(value) && Array.isArray(value[kind]) ? value[kind] as unknown[] : []
}

/**
 * Compare rule expressions independently of JSON object property order.
 * @param value The expression to serialize.
 */
function canonicalJson(value: unknown): string {
	return JSON.stringify(value, (_key, item: unknown) => {
		if (isRecord(item)) {
			return Object.fromEntries(Object.entries(item).sort(([a], [b]) => a.localeCompare(b)))
		}
		return item
	})
}

/**
 * Convert a saved rule back to the visual editor model.
 * Only conditions produced by this editor are supported.
 * @param rule Saved template rule.
 */
export function parsePdfTemplateRule(rule: PdfTemplateRule): PdfRuleDraft {
	const base: PdfRuleDraft = {
		id: rule.id,
		label: rule.label ?? '',
		kind: 'tags',
		tag1: '',
		operator: '',
		tag2: '',
		backgroundColor: rule.backgroundColor ?? null,
		cellPadding: rule.cellPadding ?? null,
	}

	const expression = rule.expression
	const matches = (draft: PdfRuleDraft) => canonicalJson(buildPdfRuleCondition(draft)) === canonicalJson(expression)

	// Break duration condition.
	const breakDraft: PdfRuleDraft = { ...base, kind: 'break' }
	if (matches(breakDraft)) {
		return breakDraft
	}

	// Single-tag condition.
	const all = readGroup(expression, 'all')
	const tag = readTag(all[0])
	if (all.length === 1 && tag !== null) {
		const draft = { ...base, tag1: tag }
		if (matches(draft)) {
			return draft
		}
	}

	// AND and OR groups containing two tag conditions.
	for (const [group, operator] of [['all', 'AND'], ['any', 'OR']] as const) {
		const nodes = readGroup(expression, group)
		const tag1 = readTag(nodes[0])
		const tag2 = readTag(nodes[1])
		if (nodes.length === 2 && tag1 !== null && tag2 !== null) {
			const draft = { ...base, tag1, tag2, operator }
			if (matches(draft)) {
				return draft
			}
		}
	}

	// XOR: (A AND NOT B) OR (NOT A AND B).
	const any = readGroup(expression, 'any')
	const first = readGroup(any[0], 'all')
	const tag1 = readTag(first[0])
	const tag2 = isRecord(first[1]) ? readTag(first[1].not) : null
	if (tag1 !== null && tag2 !== null) {
		const draft: PdfRuleDraft = { ...base, tag1, tag2, operator: 'XOR' }
		if (matches(draft)) {
			return draft
		}
	}

	throw new Error('Unsupported PDF template rule')
}

export function serializePdfRules(drafts: PdfRuleDraft[]): PdfTemplateRule[] {
	return drafts.map((draft, order) => ({
		id: draft.id,
		order,
		label: draft.label.trim() || null,
		expression: buildPdfRuleCondition(draft),
		backgroundColor: draft.backgroundColor,
		cellPadding: draft.cellPadding,
	}))
}

export function newPdfRule(): PdfRuleDraft {
	return {
		id: crypto.randomUUID(),
		label: '',
		kind: 'tags',
		tag1: '',
		operator: '',
		tag2: '',
		backgroundColor: '#FFE2B8',
		cellPadding: null,
	}
}
