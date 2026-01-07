import { createApp } from 'vue';
import BookClustersEditApp from '@/apps/BookClustersEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#book-clusters-edit-app',
    components: {
        BookClustersEditApp
    }
});


app.use(uiv);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect)
app.mount('#book-clusters-edit-app');