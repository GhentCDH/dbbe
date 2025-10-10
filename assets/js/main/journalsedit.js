import { createApp } from 'vue';
import JournalsEditApp from '@/apps/JournalsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#journals-edit-app',
    components: {
        JournalsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#journals-edit-app');