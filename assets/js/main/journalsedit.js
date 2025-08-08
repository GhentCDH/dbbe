import Vue from 'vue';
import JournalsEditApp from '@/apps/JournalsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#journals-edit-app',
    components: {
        JournalsEditApp
    }
})
