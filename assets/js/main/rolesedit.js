import RolesEditApp from '@/apps/RolesEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import { createApp } from 'vue';
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#roles-edit-app',
    components: {
        RolesEditApp
    }
});


app.use(uiv);
app.component('multiselect', VueMultiselect);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.mount('#roles-edit-app');