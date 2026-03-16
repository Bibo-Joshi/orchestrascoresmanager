import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './navigation'

const router = createRouter({
	history: createWebHistory('/index.php/apps/orchestrascoresmanager/'),
	routes,
})

export default router
