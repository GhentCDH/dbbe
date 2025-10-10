import { createApp } from 'vue';
import StatusesEditApp from '@/apps/StatusesEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#statuses-edit-app',
    components: {
        StatusesEditApp
    }
});


app.use(uiv);
app.component('multiselect', VueMultiselect);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.mount('#statuses-edit-app');