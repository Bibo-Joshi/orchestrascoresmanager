import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client'
import type { FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing folder collection versions state.
 *
 * Provides centralized state management for folder collection versions,
 * supporting switching between versions and tracking active/selected versions.
 */
export const useFolderCollectionVersionsStore = defineStore('folderCollectionVersions', () => {
	// Map of folder collection ID to its versions
	const versionsByFolderCollection = ref<Map<number, FolderCollectionVersion[]>>(new Map())

	// Map of folder collection ID to currently selected version ID
	const selectedVersionByFolderCollection = ref<Map<number, number | null>>(new Map())

	// Loading state per folder collection
	const loadingByFolderCollection = ref<Map<number, boolean>>(new Map())

	/**
	 * Get versions for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function getVersions(folderCollectionId: number): FolderCollectionVersion[] {
		return versionsByFolderCollection.value.get(folderCollectionId) || []
	}

	/**
	 * Get selected version ID for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function getSelectedVersionId(folderCollectionId: number): number | null {
		return selectedVersionByFolderCollection.value.get(folderCollectionId) ?? null
	}

	/**
	 * Get the currently selected version entity
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function getSelectedVersion(folderCollectionId: number): FolderCollectionVersion | undefined {
		const versionId = getSelectedVersionId(folderCollectionId)
		if (versionId === null) return undefined
		const versions = getVersions(folderCollectionId)
		return versions.find(v => v.id === versionId)
	}

	/**
	 * Check if a version is the active version (valid_to is null)
	 *
	 * @param version - The version to check
	 */
	function isActiveVersion(version: FolderCollectionVersion | undefined): boolean {
		return version?.validTo === null
	}

	/**
	 * Check if loading versions for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function isLoading(folderCollectionId: number): boolean {
		return loadingByFolderCollection.value.get(folderCollectionId) ?? false
	}

	/**
	 * Load versions for a folder collection from API
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param activeVersionId - The active version ID to select by default
	 */
	async function loadVersions(folderCollectionId: number, activeVersionId: number | null = null): Promise<void> {
		loadingByFolderCollection.value.set(folderCollectionId, true)
		try {
			const response = await apiClients.default.folderCollectionApiGetFolderCollectionVersions(folderCollectionId)
			const versions = response.data.ocs.data || []
			versionsByFolderCollection.value.set(folderCollectionId, versions)

			// Set selected version to active version or first version
			if (!selectedVersionByFolderCollection.value.has(folderCollectionId)) {
				if (activeVersionId !== null) {
					selectedVersionByFolderCollection.value.set(folderCollectionId, activeVersionId)
				} else if (versions.length > 0) {
					// Select the active version (validTo = null) or the latest one
					const activeVersion = versions.find(v => v.validTo === null)
					selectedVersionByFolderCollection.value.set(
						folderCollectionId,
						activeVersion?.id ?? versions[0].id,
					)
				}
			}
		} catch (error) {
			console.error(`Failed to load versions for folder collection ${folderCollectionId}:`, error)
			versionsByFolderCollection.value.set(folderCollectionId, [])
		} finally {
			loadingByFolderCollection.value.set(folderCollectionId, false)
		}
	}

	/**
	 * Set selected version for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param versionId - The version ID to select
	 */
	function setSelectedVersion(folderCollectionId: number, versionId: number | null): void {
		selectedVersionByFolderCollection.value.set(folderCollectionId, versionId)
	}

	/**
	 * Add a new version to the store
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param version - The new version to add
	 */
	function addVersion(folderCollectionId: number, version: FolderCollectionVersion): void {
		const versions = versionsByFolderCollection.value.get(folderCollectionId) || []
		// Add to the beginning (newest first)
		versionsByFolderCollection.value.set(folderCollectionId, [version, ...versions])
	}

	/**
	 * Update an existing version in the store
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param version - The updated version
	 */
	function updateVersion(folderCollectionId: number, version: FolderCollectionVersion): void {
		const versions = versionsByFolderCollection.value.get(folderCollectionId) || []
		const index = versions.findIndex(v => v.id === version.id)
		if (index !== -1) {
			versions[index] = version
			versionsByFolderCollection.value.set(folderCollectionId, [...versions])
		}
	}

	/**
	 * Clear versions for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function clearVersions(folderCollectionId: number): void {
		versionsByFolderCollection.value.delete(folderCollectionId)
		selectedVersionByFolderCollection.value.delete(folderCollectionId)
		loadingByFolderCollection.value.delete(folderCollectionId)
	}

	/**
	 * Start a new version for a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param validFrom - The start date for the new version (Y-m-d format)
	 * @return The newly created version
	 */
	async function startNewVersion(folderCollectionId: number, validFrom?: string): Promise<FolderCollectionVersion> {
		const response = await apiClients.default.folderCollectionApiStartNewVersion(
			folderCollectionId,
			validFrom ? { validFrom } : undefined,
		)
		const newVersion = response.data.ocs.data

		// Reload versions to get the updated list with deactivated previous version
		await loadVersions(folderCollectionId, newVersion.id)

		// Select the new version
		setSelectedVersion(folderCollectionId, newVersion.id)

		return newVersion
	}

	return {
		getVersions,
		getSelectedVersionId,
		getSelectedVersion,
		isActiveVersion,
		isLoading,
		loadVersions,
		setSelectedVersion,
		addVersion,
		updateVersion,
		clearVersions,
		startNewVersion,
	}
})
