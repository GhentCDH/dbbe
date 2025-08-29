import Vue from 'vue';
import fieldRadio from '../components/FormFields/fieldRadio.vue';
import VueFormGenerator from 'vue-form-generator';
import axios from 'axios';

import TypeSearchApp from '@/apps/TypeSearchApp'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
;
import fieldMultiselectClear from '../components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../components/Alerts.vue'
import fieldCheckboxes from "@/components/FormFields/fieldCheckboxes.vue";

Vue.use(uiv)
Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.use(uiv);

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
