
import LocationsEditApp  from '../apps/LocationsEditApp.vue'
import VueFormGenerator from "vue3-form-generator-legacy";
import * as uiv from "uiv";
import VueCookies from "vue-cookies";
import fieldRadio from "../Components/FormFields/fieldRadio.vue";
import fieldMultiselectClear from "../Components/FormFields/fieldMultiselectClear.vue";
import Alerts from "../Components/Alerts.vue";
import Delete from "../Components/Edit/Modals/Delete.vue";
import CollectionManager from "../Components/Search/CollectionManager.vue";

import {createApp} from "vue";

const app=createApp();
app.config.compilerOptions.whitespace='condense';

app.use(VueFormGenerator)
app.use(uiv)
app.use(VueCookies);
app.component('FieldRadio', fieldRadio);
app.component('FieldMultiselectClear', fieldMultiselectClear);
app.component('Alerts', Alerts);
app.component('DeleteModal', Delete);
app.component('CollectionManager', CollectionManager);
app.component('LocationsEditApp', LocationsEditApp);
app.mount( '#locations-edit-app');