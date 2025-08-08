import Vue from 'vue';
import ManuscriptSearchApp from '@/apps/ManuscriptSearchApp'

import VueTables from 'vue-tables-2';
import Alerts from '../Components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';
import fieldCheckboxes from '../Components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'

Vue.use(uiv);
Vue.use(VueTables.ServerTable);
Vue.use(VueFormGenerator);

Vue.component('multiselect', VueMultiselect);
Vue.component('FieldCheckboxes', fieldCheckboxes);
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
window.axios = axios;

new Vue({
    el: '#manuscript-search-app',
    components: {
        ManuscriptSearchApp
    }
})
