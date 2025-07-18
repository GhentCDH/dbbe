import Vue from 'vue';
import JournalIssuesEditApp from '@/apps/JournalIssuesEditApp'
import * as uiv from 'uiv';
import VueMultiselect from 'vue-multiselect';
import VueFormGenerator from 'vue-form-generator'
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);

new Vue({
    el: '#journal-issues-edit-app',
    components: {
        JournalIssuesEditApp
    }
})
