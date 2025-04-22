import Vue from 'vue';
import OccurrenceSearchApp from '../apps/OccurrenceSearchApp.vue'; // Root component
import * as uiv from 'uiv'; // UI library
import fieldMultiselectClear from "../Components/FormFields/fieldMultiselectClear.vue"; // Custom component
import Delete from "../Components/Edit/Modals/Delete.vue"; // Custom component
import CollectionManager from "../Components/Search/CollectionManager.vue"; // Custom component
import fieldRadio from "../Components/FormFields/fieldRadio.vue"; // Custom component
import VueCookies from "vue-cookies"; // Cookies library
import VueFormGenerator from "vue-form-generator"; // Form generator library
import Alerts from "../Components/Alerts.vue"; // Custom component

Vue.component('FieldRadio', fieldRadio);
Vue.component('FieldMultiselectClear', fieldMultiselectClear);
Vue.component('Alerts', Alerts);
Vue.component('DeleteModal', Delete);
Vue.component('CollectionManager', CollectionManager);

// Use plugins
Vue.use(VueFormGenerator);
Vue.use(uiv);
Vue.use(VueCookies);

// Create and mount the app
new Vue({
    render: h => h(OccurrenceSearchApp),
}).$mount('#occurrence-search-app');
