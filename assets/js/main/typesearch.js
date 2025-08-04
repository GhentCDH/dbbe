import Vue from 'vue';
import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import VueFormGenerator from 'vue-form-generator';
import axios from 'axios';

import TypeSearchApp from '@/apps/TypeSearchApp'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
import VueTables from 'vue-tables-2';
import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../Components/Alerts.vue'
import fieldCheckboxes from "@/Components/FormFields/fieldCheckboxes.vue";

Vue.use(uiv)
Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('FieldRadio', fieldRadio);
Vue.use(uiv);
Vue.use(VueTables.ServerTable);
Vue.component('FieldCheckboxes', fieldCheckboxes);

Vue.use(VueFormGenerator)

Vue.component('alerts', Alerts)
window.axios = axios;
Vue.component('FieldRadio', fieldRadio);

new Vue({
    el: '#type-search-app',
    components: {
        TypeSearchApp
    }
})
