import { createApp } from 'vue';
import FeedbackApp from '@/apps/FeedbackApp'
const app = createApp({
    el: '#feedback-app',
    components: {
        FeedbackApp
    }
});

app.mount('#feedback-app');