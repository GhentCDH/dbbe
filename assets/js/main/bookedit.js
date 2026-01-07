import { createApp } from 'vue';
import BookEditApp from '@/apps/BookEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';
import Panel from "@/components/Edit/Panel.vue";
const app = createApp({
    el: '#book-edit-app',
    components: {
        BookEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('field-multiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect);
app.component('panel',Panel)
app.use(VueFormGenerator);

app.mount('#book-edit-app');