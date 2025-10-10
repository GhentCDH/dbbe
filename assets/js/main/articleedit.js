import { createApp } from 'vue';
import ArticleEditApp from '@/apps/ArticleEditApp'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import VueMultiselect from 'vue-multiselect';
const app = createApp({
    el: '#article-edit-app',
    components: {
        ArticleEditApp
    }
});

app.use(uiv);
app.component('field-multiselectClear', fieldMultiselectClear)
app.component('multiselect', VueMultiselect);
app.mount('#article-edit-app');