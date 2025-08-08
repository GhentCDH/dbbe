import Vue from 'vue';
import MetresEditApp from '@/apps/MetresEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#metres-edit-app',
    components: {
        MetresEditApp
    }
})
