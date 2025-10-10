import { createApp } from 'vue';
import LocationsEditApp from '@/apps/LocationsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from "@/components/FormFields/fieldMultiselectClear.vue";
import VueMultiselect from "vue-multiselect";
const app = createApp({
    el: '#locations-edit-app',
    components: {
        LocationsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#locations-edit-app');