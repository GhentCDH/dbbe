import Vue from 'vue';
import ManuscriptEditApp from '@/apps/ManuscriptEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue-form-generator'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.component('field-multiselectClear', fieldMultiselectClear)
Vue.component('multiselect', VueMultiselect);
new Vue({
    el: '#manuscript-edit-app',
    components: {
        ManuscriptEditApp
    }
})
