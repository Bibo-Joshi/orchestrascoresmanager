module.exports = {
	globals: {
		appVersion: true,
	},
	parserOptions: {
		requireConfigFile: false,
	},
	extends: [
		'@nextcloud',
		'@nextcloud/eslint-config/typescript',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/no-multiple-template-root': 'off',
		'import/extensions': 'off',
	},
	overrides: [
		{
			files: ['*.ts', '*.tsx', '*.vue'],
			rules: {
				'@typescript-eslint/no-unused-vars': 'error',
				'@typescript-eslint/explicit-function-return-type': 'off',
				'@typescript-eslint/explicit-module-boundary-types': 'off',
				'@typescript-eslint/no-explicit-any': 'warn',
				'vue/no-v-model-argument': 'off',
			},
		},
		{
			files: ['scripts:/**/*.mjs'],
			rules: { 'no-console': 'off' },
		},
	],
}
