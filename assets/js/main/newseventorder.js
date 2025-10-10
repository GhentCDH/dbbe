import { createApp } from 'vue';
import NewsEventOrderApp from '@/apps/NewsEventOrderApp'
const app = createApp({
    el: '#news-event-order-app',
    components: {
        NewsEventOrderApp
    }
});

app.mount('#news-event-order-app');