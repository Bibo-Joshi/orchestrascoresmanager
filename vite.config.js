import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'
import { defineConfig } from 'vite'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

export default defineConfig((env) => {
	const isProduction = env.mode === 'production'

	return createAppConfig(
		{
			main: resolve(join('src', 'main.ts')),
			adminSettings: resolve(join('src', 'admin-settings.ts')),
		},
		{
			config: {
				css: {
					modules: {
						localsConvention: 'camelCase',
					},
				},
				plugins: [eslint(), stylelint()],
				resolve: {
					alias: {
						'@': resolve('src'),
					},
				},
				build: {
					cssCodeSplit: true,
				},
				// don't copy contents of the /public folder as we just include the
				// /public folder in the distribution and don't want to have duplicates
				publicDir: false,
				esbuild: {
					// Drop console.*() calls in production builds
					drop: isProduction ? ['console'] : [],
				},
			},
			minify: isProduction,
			createEmptyCSSEntryPoints: true,
			extractLicenseInformation: true,
			thirdPartyLicense: false,
		},
	)(env)
})
