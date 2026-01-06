import { createApp } from 'vue';
import PageEditApp from '@/apps/PageEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import * as uiv from "uiv";
const app = createApp({
    el: '#page-edit-app',
    components: {
        PageEditApp
    }
});

app.use(uiv);
app.use(VueFormGenerator);
app.mount('#page-edit-app');