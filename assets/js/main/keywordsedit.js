import { createApp } from 'vue';
import KeywordsEditApp from '@/apps/KeywordsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#keywords-edit-app',
    components: {
        KeywordsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#keywords-edit-app');