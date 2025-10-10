import { createApp } from 'vue';
import AcknowledgementsEditApp from '@/apps/AcknowledgementsEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#acknowledgements-edit-app',
    components: {
        AcknowledgementsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#acknowledgements-edit-app');