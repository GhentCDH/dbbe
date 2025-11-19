import { createApp } from 'vue';
import OccurrenceEditApp from '@/apps/OccurrenceEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';
import Panel from "@/components/Edit/Panel.vue";
import AutoDate from "@/components/Edit/Panels/Components/AutoDate.vue";
const app = createApp({
    el: '#occurrence-edit-app',
    components: {
        OccurrenceEditApp
    }
});


app.use(uiv);
app.component('field-multiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect);
app.component('panel',Panel)
app.use(VueFormGenerator);

app.component('autoDate', AutoDate);

app.mount('#occurrence-edit-app');