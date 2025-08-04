import Vue from 'vue';
import PersonSearchApp from '@/apps/PersonSearchApp'
import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import VueTables from 'vue-tables-2';
import Alerts from '../Components/Alerts.vue'
import axios from 'axios';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';
import fieldCheckboxes from '../Components/FormFields/fieldCheckboxes.vue';
import * as uiv from 'uiv'

Vue.use(uiv);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.use(VueFormGenerator);
Vue.component('FieldCheckboxes', fieldCheckboxes);


Vue.component('FieldRadio', fieldRadio);
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
