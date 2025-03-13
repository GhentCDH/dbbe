import createApp from 'vue';
import BookEditApp from "../apps/BookEditApp.vue";

createApp({
    el: '#book-edit-app',
    components: {
        BookEditApp,
    },
});
