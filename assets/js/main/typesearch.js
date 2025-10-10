import { createApp } from 'vue';
import fieldRadio from '../components/FormFields/fieldRadio.vue';
import VueFormGenerator from 'vue3-form-generator-legacy';
import axios from 'axios';

import TypeSearchApp from '@/apps/TypeSearchApp'
import * as uiv from 'uiv'
import Alerts from '../components/Alerts.vue'
import fieldCheckboxes from "@/components/FormFields/fieldCheckboxes.vue";
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";

const app = createApp({});
app.use(uiv)
app.use(uiv);
app.component('FieldCheckboxes', fieldCheckboxes);

app.use(VueFormGenerator)
app.component('alerts', Alerts)
window.axios = axios;
app.component('FieldRadio', fieldRadio);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.component('type-search-app', TypeSearchApp)
app.mount('#type-search-app')