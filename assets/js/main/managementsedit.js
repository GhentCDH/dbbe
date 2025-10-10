import { createApp } from 'vue';
import ManagementsEditApp from '@/apps/ManagementsEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import * as uiv from 'uiv';
const app = createApp({
    el: '#managements-edit-app',
    components: {
        ManagementsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#managements-edit-app');