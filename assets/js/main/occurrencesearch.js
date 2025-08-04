import Vue from 'vue';
import OccurrenceSearchApp from '../apps/OccurrenceSearchApp.vue'; // Root component
import Delete from "../Components/Edit/Modals/Delete.vue"; // Custom component
import CollectionManager from "../Components/Search/CollectionManager.vue"; // Custom component
import VueCookies from "vue-cookies"; // Cookies library
import VueFormGenerator from "vue-form-generator"; // Form generator library
import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import VueTables from 'vue-tables-2';
import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../Components/Alerts.vue'
import axios from 'axios';
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
import fieldCheckboxes from "@/Components/FormFields/fieldCheckboxes.vue";

window.axios = axios;


Vue.use(uiv);
Vue.use(VueTables.ServerTable);
Vue.component('multiselect', VueMultiselect)
Vue.component('FieldRadio', fieldRadio);
Vue.component('FieldMultiselectClear', fieldMultiselectClear);
Vue.component('Alerts', Alerts);
Vue.component('DeleteModal', Delete);
Vue.component('CollectionManager', CollectionManager);
Vue.component('FieldCheckboxes', fieldCheckboxes);


// Use plugins
Vue.use(VueFormGenerator);
Vue.use(uiv);
Vue.use(VueCookies);

window.axios = axios;

new Vue({
    el: '#occurrence-search-app',
    components: {
        OccurrenceSearchApp
    }
})
