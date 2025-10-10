import { createApp } from 'vue';
import MetresEditApp from '@/apps/MetresEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#metres-edit-app',
    components: {
        MetresEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#metres-edit-app');