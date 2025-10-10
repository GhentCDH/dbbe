import { createApp } from 'vue';
import JournalsEditApp from '@/apps/JournalsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#journals-edit-app',
    components: {
        JournalsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#journals-edit-app');