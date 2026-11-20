import type { TopLevelCondition } from 'json-rules-engine'
import type { Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import type { PdfTemplateRule } from '@/utils/pdfTemplatesApi'

import { Engine } from 'json-rules-engine'

interface RuleFacts {
	setlist: Setlist & { tags: string[] }
	setlistentry: SetlistEntry & { tags: string[] }
}

/**
 * Resolve JSON paths for record properties and array indices without evaluating expressions.
 * @param value The fact value.
 * @param path The JSON path.
 */
function resolvePath(value: object, path: string): unknown {
	if (!/^\$(?:\.[a-zA-Z_][a-zA-Z_0-9]*|\[\d+\])*$/.test(path)) {
		return undefined
	}
	const keys = path.slice(1).matchAll(/\.([a-zA-Z_][a-zA-Z_0-9]*)|\[(\d+)\]/g)
	let result: unknown = value
	for (const [, property, index] of keys) {
		const key = property ?? index
		if (key === undefined || typeof result !== 'object' || result === null
			|| !Object.getOwnPropertyDescriptor(result, key)) {
			return undefined
		}
		result = (result as Record<string, unknown>)[key]
	}
	return result
}

function normalizeTag(tag: string): string {
	return tag.trim().toLocaleLowerCase()
}

/**
 * Validate boolean groups and adapt tag membership to the legacy comparison.
 * @param expression A stored condition or group.
 */
function prepareCondition(expression: unknown): Record<string, unknown> {
	if (!expression || typeof expression !== 'object' || Array.isArray(expression)) {
		throw new Error('Expected a PDF rule condition object')
	}
	const node = expression as Record<string, unknown>
	const groups = ['all', 'any', 'not'].filter((key) => Object.getOwnPropertyDescriptor(node, key))
	const group = groups[0]
	if (groups.length > 1 || (group && ('fact' in node || 'operator' in node || 'condition' in node))) {
		throw new Error('Ambiguous PDF rule condition')
	}
	if (group === 'not') {
		return { ...node, not: prepareCondition(node.not) }
	}
	if (group === 'all' || group === 'any') {
		const children = node[group]
		if (!Array.isArray(children) || children.length === 0) {
			throw new Error('Expected a non-empty PDF rule condition group')
		}
		return { ...node, [group]: children.map(prepareCondition) }
	}
	if ((node.fact === 'setlist' || node.fact === 'setlistentry')
		&& node.path === '$.tags' && node.operator === 'contains') {
		return { ...node, operator: 'containsPdfTag' }
	}
	return node
}

/**
 * Compile rules in priority order. A separate engine per rule lets evaluation
 * stop at the first match, even when that rule has no formatting overrides.
 * @param rules Template rules sorted by ascending order.
 */
export function createRuleMatcher(rules: PdfTemplateRule[]) {
	const compiled = rules.map((rule) => {
		// The editor stores a single condition directly, while the engine
		// requires a boolean group at the root.
		const expression = prepareCondition(rule.expression)
		const conditions = ['all', 'any', 'not'].some((key) => Object.getOwnPropertyDescriptor(expression, key))
			? expression
			: { all: [expression] }
		const engine = new Engine([{
			conditions: conditions as TopLevelCondition,
			event: { type: 'format-row' },
		}], { pathResolver: resolvePath })
		engine.addOperator<unknown, unknown>('containsPdfTag', (tags, expected) => {
			if (!Array.isArray(tags) || typeof expected !== 'string') {
				throw new Error('Expected a tag list and a tag name')
			}
			const name = normalizeTag(expected)
			return name !== '' && tags.some((tag) => typeof tag === 'string' && normalizeTag(tag) === name)
		})
		return { rule, engine }
	})

	return async (facts: RuleFacts): Promise<PdfTemplateRule | undefined> => {
		for (const { rule, engine } of compiled) {
			const { events } = await engine.run({ ...facts })
			if (events.length) {
				return rule
			}
		}
	}
}
