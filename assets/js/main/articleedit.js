import {createApp} from "vue";
import * as uiv from 'uiv'
import fieldMultiselectClear from "../Components/FormFields/fieldMultiselectClear.vue";
import Delete from "../Components/Edit/Modals/Delete.vue";
import CollectionManager from "../Components/Search/CollectionManager.vue";
import fieldRadio from "../Components/FormFields/fieldRadio.vue";
import VueCookies from "vue-cookies";
import VueFormGenerator from "vue3-form-generator-legacy";
import Alerts from "../Components/Alerts.vue";

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
app.mount( '#article-edit-app');
