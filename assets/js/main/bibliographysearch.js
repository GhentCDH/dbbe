import { createApp } from 'vue';
import BibliographySearchApp from '@/apps/BibliographySearchApp'
import fieldRadio from '../components/FormFields/fieldRadio.vue';
import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
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
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
window.axios = axios;
app.mount('#bibliography-search-app');