import Vue from 'vue';
import BibliographySearchApp from '@/apps/BibliographySearchApp'
import fieldRadio from '../components/FormFields/fieldRadio.vue';
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
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.component('FieldCheckboxes', fieldCheckboxes);
Vue.component('FieldRadio', fieldRadio);
Vue.component('alerts', Alerts)

window.axios = axios;

new Vue({
    el: '#bibliography-search-app',
    components: {
        BibliographySearchApp
    }
})
