import { recommended } from '@nextcloud/eslint-config'

const isProduction = process.env.NODE_ENV === 'production'

export default [
	...recommended,

	{
		name: 'app/global-overrides',
		languageOptions: {
			globals: {
				appVersion: 'readonly',
			},
		},
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/no-multiple-template-root': 'off',
			'vue/multi-word-component-names': 'off',
			'import/extensions': 'off',
			'no-console': isProduction ? 'error' : 'off',
		},
	},

	{
		name: 'app/typescript-vue-overrides',
		files: ['**/*.ts', '**/*.tsx', '**/*.vue'],
		rules: {
			'@typescript-eslint/no-unused-vars': 'error',
			'@typescript-eslint/explicit-function-return-type': 'off',
			'@typescript-eslint/explicit-module-boundary-types': 'off',
			'@typescript-eslint/no-explicit-any': 'warn',
			'vue/no-v-model-argument': 'off',
		},
	},

	{
		name: 'app/scripts-overrides',
		files: ['scripts/**/*.mjs'],
		rules: {
			'no-console': 'off',
		},
	},
]
