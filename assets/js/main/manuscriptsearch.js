import { createApp } from 'vue';
import ManuscriptSearchApp from '@/apps/ManuscriptSearchApp'


import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'
const app = createApp({
    el: '#manuscript-search-app',
    components: {
        ManuscriptSearchApp
    }
});

app.use(uiv);

app.use(VueFormGenerator);
app.component('FieldCheckboxes', fieldCheckboxes);
app.component('alerts', Alerts)
window.axios = axios;
app.mount('#manuscript-search-app');