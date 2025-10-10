import { createApp } from 'vue';
import ContentsEditApp from '@/apps/ContentsEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#contents-edit-app',
    components: {
        ContentsEditApp
    }
});


app.use(uiv);
app.component('multiselect', VueMultiselect);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.mount('#contents-edit-app');