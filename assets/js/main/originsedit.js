import { createApp } from 'vue';
import OriginsEditApp from '@/apps/OriginsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#origins-edit-app',
    components: {
        OriginsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#origins-edit-app');