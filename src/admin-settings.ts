import { createApp } from 'vue'
import AdminSettings from './components/AdminSettings.vue'
import './types/nextcloud.d.ts'

const app = createApp(AdminSettings)
app.mount('#orchestra-scores-admin-settings')
