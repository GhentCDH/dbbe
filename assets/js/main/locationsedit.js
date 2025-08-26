import Vue from 'vue';
import LocationsEditApp from '@/apps/LocationsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#locations-edit-app',
    components: {
        LocationsEditApp
    }
})
