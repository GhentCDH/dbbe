import { createApp } from 'vue';
import SelfDesignationsEditApp from '@/apps/SelfDesignationsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#self-designations-edit-app',
    components: {
        SelfDesignationsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#self-designations-edit-app');