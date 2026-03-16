import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './types/nextcloud.d.ts'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { useScoreBookSidebarStore } from '@/stores/scoreBookSidebarStore'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'

const pinia = createPinia()
const app = createApp(App)

app.use(pinia)
app.use(router)

const scoreSidebarStore = useScoreSidebarStore()
const scoreBookSidebarStore = useScoreBookSidebarStore()
const setlistSidebarStore = useSetlistSidebarStore()

router.afterEach(() => {
	// Close sidebars on route change
	scoreSidebarStore.closeSidebar()
	scoreBookSidebarStore.closeSidebar()
	setlistSidebarStore.closeSidebar()
})

app.mount('#content')
