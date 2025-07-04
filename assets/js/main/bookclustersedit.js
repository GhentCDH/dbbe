import Vue from 'vue';
import BookClustersEditApp from '@/apps/BookClustersEditApp'
import VueFormGenerator from 'vue-form-generator'
Vue.use(VueFormGenerator);

new Vue({
    el: '#book-clusters-edit-app',
    components: {
        BookClustersEditApp
    }
})
