import { createApp } from 'vue';
import BookSeriessEditApp from '@/apps/BookSeriessEditApp'
import * as uiv from 'uiv';
import VueFormGenerator from 'vue3-form-generator-legacy'
const app = createApp({
    el: '#book-seriess-edit-app',
    components: {
        BookSeriessEditApp
    }
});


app.use(uiv);
app.use(VueFormGenerator);
app.mount('#book-seriess-edit-app');