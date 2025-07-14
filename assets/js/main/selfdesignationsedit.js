import Vue from 'vue';
import SelfDesignationsEditApp from '@/apps/SelfDesignationsEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#self-designations-edit-app',
    components: {
        SelfDesignationsEditApp
    }
})
