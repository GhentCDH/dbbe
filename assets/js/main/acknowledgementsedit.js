import Vue from 'vue';
import AcknowledgementsEditApp from '@/apps/AcknowledgementsEditApp'
import VueFormGenerator from 'vue-form-generator'
Vue.use(VueFormGenerator);

new Vue({
    el: '#acknowledgements-edit-app',
    components: {
        AcknowledgementsEditApp
    }
})
