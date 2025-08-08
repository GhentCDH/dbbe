import Vue from 'vue';
import PersonEditApp from '@/apps/PersonEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue-form-generator'
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.component('field-multiselectClear', fieldMultiselectClear)
Vue.component('multiselect', VueMultiselect);

new Vue({
    el: '#person-edit-app',
    components: {
        PersonEditApp
    }
})
