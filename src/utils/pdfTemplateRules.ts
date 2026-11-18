import type { PdfTemplateCondition, PdfTemplateRule } from '@/utils/pdfTemplatesApi'

export type PdfRuleOperator = '' | 'AND' | 'OR' | 'XOR'
export type PdfRuleKind = 'tags' | 'break' | 'unsupported'

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
	originalExpression: PdfTemplateCondition | null
}

function tagCondition(tag: string): PdfTemplateCondition {
	return { fact: 'setlistentry', path: '$.tags', operator: 'contains', value: tag }
}

function breakCondition(): PdfTemplateCondition {
	return { fact: 'setlistentry', path: '$.breakDuration', operator: 'greaterThan', value: 0 }
}

/**
 * Conditions use the JSON Rules Engine AST (all/any/not).
 * @param draft Editor rule definition.
 */
export function buildPdfRuleCondition(draft: PdfRuleDraft): PdfTemplateCondition {
	if (draft.kind === 'unsupported') {
		return draft.originalExpression ?? {}
	}
	if (draft.kind === 'break') {
		return breakCondition()
	}

	const first = tagCondition(draft.tag1.trim())
	if (!draft.operator) {
		return first
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

function readTag(value: unknown): string | null {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return null
	}
	const leaf = value as Record<string, unknown>
	return leaf.fact === 'setlistentry' && leaf.path === '$.tags'
		&& leaf.operator === 'contains' && typeof leaf.value === 'string'
		? leaf.value
		: null
}

function readPair(value: unknown, kind: 'all' | 'any'): [string, string] | null {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return null
	}
	const nodes = (value as Record<string, unknown>)[kind]
	if (!Array.isArray(nodes) || nodes.length !== 2) {
		return null
	}
	const first = readTag(nodes[0])
	const second = readTag(nodes[1])
	return first !== null && second !== null ? [first, second] : null
}

/**
 * Decode the conditions supported by the visual editor. Other library ASTs
 * remain intact and are displayed read-only, never silently overwritten.
 * @param rule Saved shared template rule.
 */
export function editPdfRule(rule: PdfTemplateRule): PdfRuleDraft {
	const base: PdfRuleDraft = {
		id: rule.id,
		label: rule.label ?? '',
		kind: 'unsupported',
		tag1: '',
		operator: '',
		tag2: '',
		backgroundColor: rule.backgroundColor,
		cellPadding: rule.cellPadding,
		originalExpression: rule.expression,
	}

	if (JSON.stringify(rule.expression) === JSON.stringify(breakCondition())) {
		return { ...base, kind: 'break' }
	}
	const single = readTag(rule.expression)
	if (single !== null) {
		return { ...base, kind: 'tags', tag1: single }
	}
	for (const [logical, op] of [['all', 'AND'], ['any', 'OR']] as const) {
		const pair = readPair(rule.expression, logical)
		if (pair) {
			return { ...base, kind: 'tags', tag1: pair[0], tag2: pair[1], operator: op }
		}
	}

	// Recognize the exact XOR AST emitted by this editor.
	const any = rule.expression.any
	if (Array.isArray(any) && any.length === 2) {
		const firstPart = any[0]
		if (firstPart && typeof firstPart === 'object' && !Array.isArray(firstPart)) {
			const all = (firstPart as Record<string, unknown>).all
			if (Array.isArray(all) && all.length === 2) {
				const tag1 = readTag(all[0])
				const notNode = all[1]
				const tag2 = notNode && typeof notNode === 'object' && !Array.isArray(notNode)
					? readTag((notNode as Record<string, unknown>).not)
					: null
				if (tag1 !== null && tag2 !== null) {
					const candidate = { ...base, kind: 'tags' as const, tag1, tag2, operator: 'XOR' as const }
					if (JSON.stringify(buildPdfRuleCondition(candidate)) === JSON.stringify(rule.expression)) {
						return candidate
					}
				}
			}
		}
	}

	return base
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
		id: `rule-${Math.random().toString(36).slice(2, 12)}`,
		label: '',
		kind: 'tags',
		tag1: '',
		operator: '',
		tag2: '',
		backgroundColor: '#FFE2B8',
		cellPadding: null,
		originalExpression: null,
	}
}
