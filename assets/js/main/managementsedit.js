import { createApp } from 'vue';
import ManagementsEditApp from '@/apps/ManagementsEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import * as uiv from 'uiv';
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#managements-edit-app',
    components: {
        ManagementsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#managements-edit-app');