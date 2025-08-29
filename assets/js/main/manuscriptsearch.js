import Vue from 'vue';
import ManuscriptSearchApp from '@/apps/ManuscriptSearchApp'

;
import Alerts from '../components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import fieldCheckboxes from '../components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'

Vue.use(uiv);

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
