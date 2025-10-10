import { createApp } from 'vue';
import RegionsEditApp from '@/apps/RegionsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#regions-edit-app',
    components: {
        RegionsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#regions-edit-app');