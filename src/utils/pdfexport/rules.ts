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

/**
 * Compile rules in priority order. A separate engine per rule lets evaluation
 * stop at the first match, even when that rule has no formatting overrides.
 * @param rules Template rules sorted by ascending order.
 */
export function createRuleMatcher(rules: PdfTemplateRule[]) {
	const compiled = rules.map((rule) => {
		// The editor stores a single condition directly, while the engine
		// requires a boolean group at the root.
		const conditions = ['all', 'any', 'not'].some((key) => Object.getOwnPropertyDescriptor(rule.expression, key))
			? rule.expression
			: { all: [rule.expression] }
		const engine = new Engine([{
			conditions: conditions as TopLevelCondition,
			event: { type: 'format-row' },
		}], { pathResolver: resolvePath })
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
