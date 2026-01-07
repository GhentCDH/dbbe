import { createApp } from 'vue';
import JournalIssuesEditApp from '@/apps/JournalIssuesEditApp'
import * as uiv from 'uiv';
import VueMultiselect from 'vue-multiselect';
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
const app = createApp({
    el: '#journal-issues-edit-app',
    components: {
        JournalIssuesEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('multiselect', VueMultiselect);
app.component('field-multiselect', fieldMultiselectClear);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.mount('#journal-issues-edit-app');