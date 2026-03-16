import { markRaw } from 'vue'
import { NavigationItem, NavigationRoute } from './types/navigation'
import { ScoreIcon, ScoreBookIcon, FolderCollectionIcon, SetlistIcon } from '@/icons/vue-material'
import { t } from './utils/l10n'

const APP_NAME = t('Orchestra Scores Manager')

/**
 * Centralized navigation configuration
 * Single source of truth for routes and navigation items
 */
const navigation: NavigationItem[] = [
	{
		path: '/scores',
		name: 'scores',
		displayName: t('Scores'),
		component: () => import('./pages/scores/Index.vue'),
		icon: markRaw(ScoreIcon),
	},
	{
		path: '/scorebooks',
		name: 'scorebooks',
		displayName: t('Score Books'),
		component: () => import('./pages/scorebooks/Index.vue'),
		icon: markRaw(ScoreBookIcon),
	},
	{
		path: '/foldercollections',
		name: 'foldercollections',
		displayName: t('Folder Collections'),
		component: () => import('./pages/foldercollections/Index.vue'),
		icon: markRaw(FolderCollectionIcon),
	},
	{
		path: '/setlists',
		name: 'setlists',
		displayName: t('Setlists'),
		component: () => import('./pages/setlists/Index.vue'),
		icon: markRaw(SetlistIcon),
	},
]

/**
 * Generate routes array for Vue Router
 * Uses the component references from navigation config
 */
const routes: NavigationRoute[] = [
	...navigation.map(item => ({
		path: item.path,
		name: item.name,
		component: item.component,
		meta: {
			displayName: item.displayName,
		},
	})),
	// Dynamic route for individual score book
	{
		path: '/scorebooks/:id',
		name: 'scorebook',
		component: () => import('./pages/scorebook/Index.vue'),
		meta: {
			displayName: t('Score Book'),
		},
	},
	// Dynamic route for individual folder collection
	{
		path: '/foldercollections/:id',
		name: 'foldercollection',
		component: () => import('./pages/foldercollection/Index.vue'),
		meta: {
			displayName: t('Folder Collection'),
		},
	},
	// Dynamic route for individual set lists
	{
		path: '/setlists/:id',
		name: 'setlist',
		component: () => import('./pages/setlist/Index.vue'),
		meta: {
			displayName: t('Setlist'),
		},
	},
]

/**
 * Generate browser tab title based on page title
 * @param pageTitle - The page title or null
 */
const generateFullTitle = (pageTitle: string | null | undefined): string => {
	return pageTitle
		? `${pageTitle} - ${APP_NAME} - Nextcloud`
		: `${APP_NAME} - Nextcloud`
}

export {
	navigation,
	routes,
	generateFullTitle,
	APP_NAME,
}
