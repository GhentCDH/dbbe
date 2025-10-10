import { createApp } from 'vue';
import ManuscriptEditApp from '@/apps/ManuscriptEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';
const app = createApp({
    el: '#manuscript-edit-app',
    components: {
        ManuscriptEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('field-multiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect);
app.mount('#manuscript-edit-app');