import { createApp } from 'vue';
import RegionsEditApp from '@/apps/RegionsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#regions-edit-app',
    components: {
        RegionsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#regions-edit-app');