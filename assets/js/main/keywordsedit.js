import Vue from 'vue';
import KeywordsEditApp from '@/apps/KeywordsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#keywords-edit-app',
    components: {
        KeywordsEditApp
    }
})
