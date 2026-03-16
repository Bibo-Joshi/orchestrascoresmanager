import path from 'node:path'
import { fileURLToPath } from 'node:url'
import fs from 'node:fs/promises'
import * as glob from 'glob'
import { generateApi } from 'swagger-typescript-api'

// This script finds openapi*.json files in the repository root and
// generates TypeScript axios-based clients into src/generated/api/<name>

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

async function main() {
	const root = path.resolve(__dirname, '..')
	const pattern = 'openapi*.json'
	const files = glob.sync(pattern, { nodir: true, cwd: root, absolute: true })

	if (files.length === 0) {
		console.warn('No openapi*.json files found in project root. Nothing to do.')
		return
	}

	for (const file of files) {
		// skip openapi-full - it's just the super set
		if (path.basename(file).includes('-full')) {
			continue
		}
		const base = path.basename(file, path.extname(file))
		const outDir = path.join(root, 'src', 'api', 'generated', base)

		// Ensure outDir exists
		await fs.mkdir(outDir, { recursive: true })

		console.info(`Generating API for ${file} -> ${outDir}`)

		await generateApi({
			input: file,
			output: outDir,
			httpClientType: 'axios',
			apiName: 'api.ts',
			generateUnionEnums: true,
			modular: true,
			extractRequestBody: true,
			extractResponseBody: true,
		})

		// Touch a README so devs know this is generated
		const readme = `# Generated API: ${base}\n\nDo not edit generated files. Run \`npm run generate:api\` to re-generate.`
		await fs.writeFile(path.join(outDir, 'README.md'), readme, 'utf8')
	}

	console.info('Done generating APIs.')
}

main().catch((err) => {
	console.error(err)
	throw err
})
