import GenresEditApp from '@/apps/GenresEditApp'
import VueFormGenerator from 'vue-form-generator'
import Vue from 'vue';
import VueMultiselect from 'vue-multiselect';
import fieldMultiselectClear from '@/Components/FormFields/fieldMultiselectClear.vue';
import * as uiv from 'uiv';

Vue.use(uiv);
Vue.component('multiselect', VueMultiselect);
Vue.component('field-multiselect', fieldMultiselectClear);
Vue.use(VueFormGenerator);
// Patch Vue.prototype.$emit to log all emitted events with their arguments
const originalEmit = Vue.prototype.$emit;

Vue.prototype.$emit = function(event, ...args) {
    console.log(`[Event emitted] ${event}`, ...args);
    return originalEmit.apply(this, [event, ...args]);
};

new Vue({
    el: '#genres-edit-app',
    components: {
        GenresEditApp
    }
})
