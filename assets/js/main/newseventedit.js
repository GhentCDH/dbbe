import { createApp } from 'vue';
import NewsEventEditApp from '@/apps/NewsEventEditApp'
const app = createApp({
    el: '#news-event-edit-app',
    components: {
        NewsEventEditApp
    }
});

app.mount('#news-event-edit-app');