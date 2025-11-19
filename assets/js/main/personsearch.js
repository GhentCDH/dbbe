import { createApp } from 'vue';
import PersonSearchApp from '@/apps/PersonSearchApp'

import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#person-search-app',
    components: {
        PersonSearchApp
    }
});

app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldCheckboxes', fieldCheckboxes);
app.component('alerts', Alerts)
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
window.axios = axios;

app.mount('#person-search-app');