import Vue from 'vue';
import PersonSearchApp from '@/apps/PersonSearchApp'
import VueTables from 'vue-tables-2';
import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'

Vue.use(uiv);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.use(VueFormGenerator);
Vue.component('FieldCheckboxes', fieldCheckboxes);


Vue.use(VueTables.ServerTable);
Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
window.axios = axios;

new Vue({
    el: '#person-search-app',
    components: {
        PersonSearchApp
    }
})
