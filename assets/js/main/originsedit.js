import Vue from 'vue';
import OriginsEditApp from '@/apps/OriginsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#origins-edit-app',
    components: {
        OriginsEditApp
    }
})
