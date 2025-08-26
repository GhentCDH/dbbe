import Vue from 'vue';
import BookSeriessEditApp from '@/apps/BookSeriessEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);

new Vue({
    el: '#book-seriess-edit-app',
    components: {
        BookSeriessEditApp
    }
})
