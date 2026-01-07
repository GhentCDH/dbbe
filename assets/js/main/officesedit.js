import { createApp } from 'vue';
import OfficesEditApp from '@/apps/OfficesEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#offices-edit-app',
    components: {
        OfficesEditApp
    }
});


app.use(uiv);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#offices-edit-app');