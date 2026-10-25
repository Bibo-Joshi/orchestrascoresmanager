import type { SetlistShareModel } from '../utils/setlistShareModel.ts'

import { showSuccess } from '@nextcloud/dialogs'
import { toBlob } from 'html-to-image'
import { createApp, nextTick, ref } from 'vue'
import SetlistShareImage from '../components/SetlistShareImage.vue'
import { t } from '@/utils/l10n'

/**
 * Sanitize a string for use as file name.
 *
 * @param name - Raw name
 * @return File-system safe name
 */
function sanitizeFileName(name: string): string {
	return name.replace(/[<>:"/\\|?*]/g, '_')
}

/**
 * Render the share image component off-screen and convert it to a PNG blob.
 * The component is mounted transiently in `document.body` and always cleaned up.
 *
 * @param model - Normalized share model
 * @return PNG blob
 */
async function renderModelToBlob(model: SetlistShareModel): Promise<Blob> {
	const host = document.createElement('div')
	host.style.cssText = 'position:fixed;top:0;left:-10000px;pointer-events:none;'
	document.body.appendChild(host)
	const app = createApp(SetlistShareImage, { model })
	try {
		app.mount(host)
		await nextTick()
		await document.fonts?.ready
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
		const node = host.firstElementChild as HTMLElement
		const blob = await toBlob(node, { pixelRatio: 2, backgroundColor: '#ffffff' })
		if (!blob) {
			throw new Error(t('Image could not be generated'))
		}
		return blob
	} finally {
		app.unmount()
		host.remove()
	}
}

/**
 * Download a blob as file via a temporary object URL.
 *
 * @param blob - Blob to download
 * @param fileName - Name of the downloaded file
 */
function downloadBlob(blob: Blob, fileName: string): void {
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = fileName
	document.body.appendChild(link)
	link.click()
	link.remove()
	URL.revokeObjectURL(url)
}

/**
 * Composable orchestrating the share flow: render image, create file and share it
 * via the Web Share API. Falls back to downloading the image and copying the text.
 *
 * @return Sharing state and the `share` function
 */
export function useSetlistShare() {
	const isSharing = ref(false)

	/**
	 * Share the setlist described by the model as image plus text.
	 *
	 * @param model - Normalized share model
	 */
	async function share(model: SetlistShareModel): Promise<void> {
		isSharing.value = true
		try {
			const blob = await renderModelToBlob(model)
			const fileName = `${sanitizeFileName(model.title)}.png`
			const file = new File([blob], fileName, { type: 'image/png' })
			const shareData: ShareData = { files: [file], text: model.shareText, title: model.title }

			if (typeof navigator.share === 'function' && navigator.canShare?.(shareData)) {
				try {
					await navigator.share(shareData)
				} catch (error) {
					if (error instanceof DOMException && error.name === 'AbortError') {
						return
					}
					throw error
				}
				return
			}

			downloadBlob(blob, fileName)
			await navigator.clipboard.writeText(model.shareText)
			showSuccess(t('Image downloaded and share text copied to clipboard'))
		} finally {
			isSharing.value = false
		}
	}

	return { isSharing, share }
}
