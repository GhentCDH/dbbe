import { createApp } from 'vue';
import BibliographySearchApp from '@/apps/BibliographySearchApp'
import fieldRadio from '../components/FormFields/fieldRadio.vue';
import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'
const app = createApp({
    el: '#bibliography-search-app',
    components: {
        BibliographySearchApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldCheckboxes', fieldCheckboxes);
app.component('FieldRadio', fieldRadio);
app.component('alerts', Alerts)

window.axios = axios;
app.mount('#bibliography-search-app');