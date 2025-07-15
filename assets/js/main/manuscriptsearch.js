import Vue from 'vue';
import ManuscriptSearchApp from '@/apps/ManuscriptSearchApp'

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import VueTables from 'vue-tables-2';
import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../Components/Alerts.vue'
import axios from 'axios';
import VueMultiselect from 'vue-multiselect'


import * as uiv from 'uiv'
Vue.use(uiv);

Vue.component('FieldRadio', fieldRadio);
Vue.use(VueTables.ServerTable);
Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
window.axios = axios;

new Vue({
    el: '#manuscript-search-app',
    components: {
        ManuscriptSearchApp
    }
})
