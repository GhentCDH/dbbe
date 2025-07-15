import Vue from 'vue';
import OfficesEditApp from '@/apps/OfficesEditApp'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';

Vue.use(uiv);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.use(VueFormGenerator);

new Vue({
    el: '#offices-edit-app',
    components: {
        OfficesEditApp
    }
})
