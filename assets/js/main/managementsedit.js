import Vue from 'vue';
import ManagementsEditApp from '@/apps/ManagementsEditApp'
import VueFormGenerator from 'vue-form-generator'
import * as uiv from 'uiv';

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#managements-edit-app',
    components: {
        ManagementsEditApp
    }
})
