import { createApp } from 'vue';
import LocationsEditApp from '@/apps/LocationsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#locations-edit-app',
    components: {
        LocationsEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#locations-edit-app');