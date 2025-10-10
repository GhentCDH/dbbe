import GenresEditApp from '@/apps/GenresEditApp'
import VueFormGenerator from 'vue3-form-generator-legacy'
import { createApp } from 'vue';
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';
const app = createApp({
    el: '#genres-edit-app',
    components: {
        GenresEditApp
    }
});


app.use(uiv);
app.component('multiselect', VueMultiselect);
app.component('field-multiselect', fieldMultiselectClear);
app.use(VueFormGenerator);
app.config.globalProperties.$emit = function (event, ...args) {
    console.log('Emitted event:', event, 'Args:', args)
    if (this.$.emit) {
        return this.$.emit(event, ...args)
    }
}
app.mount('#genres-edit-app');