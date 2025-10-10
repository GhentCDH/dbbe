import { createApp } from 'vue';
import PageEditApp from '@/apps/PageEditApp'
const app = createApp({
    el: '#page-edit-app',
    components: {
        PageEditApp
    }
});

app.mount('#page-edit-app');