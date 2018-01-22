import Vue from 'vue'
import UsersApp from '../apps/UsersApp'

new Vue({
    el: '#users-app',
    template: '<UsersApp />',
    components: {
        UsersApp
    }
})
