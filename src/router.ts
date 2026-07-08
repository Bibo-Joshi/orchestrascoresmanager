import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './navigation'
import { generateUrl } from '@nextcloud/router'

const router = createRouter({
	history: createWebHistory(generateUrl('/apps/orchestrascoresmanager')),
	routes,
})

export default router
