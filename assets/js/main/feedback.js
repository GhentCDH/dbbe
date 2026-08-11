import { createApp } from 'vue';
import FeedbackApp from '@/apps/FeedbackApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#feedback-app',
    components: {
        FeedbackApp
    }
});

app.use(uiv);
app.use(VueFormGenerator);

app.mount('#feedback-app');