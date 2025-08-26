import Vue from 'vue';
import RegionsEditApp from '@/apps/RegionsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#regions-edit-app',
    components: {
        RegionsEditApp
    }
})
