import { createApp } from 'vue';
import MetresEditApp from '@/apps/MetresEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#metres-edit-app',
    components: {
        MetresEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#metres-edit-app');