import { createApp } from 'vue';
import OriginsEditApp from '@/apps/OriginsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#origins-edit-app',
    components: {
        OriginsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#origins-edit-app');