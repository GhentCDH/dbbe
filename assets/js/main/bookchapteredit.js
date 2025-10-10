import { createApp } from 'vue';
import BookChapterEditApp from '@/apps/BookChapterEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';
const app = createApp({
    el: '#book-chapter-edit-app',
    components: {
        BookChapterEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.component('field-multiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect);
app.mount('#book-chapter-edit-app');