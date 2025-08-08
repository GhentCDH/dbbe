import Vue from 'vue';
import ContentsEditApp from '@/apps/ContentsEditApp'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';

Vue.use(uiv);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.use(VueFormGenerator);
new Vue({
    el: '#contents-edit-app',
    components: {
        ContentsEditApp
    }
})
