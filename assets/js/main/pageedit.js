import PageEditApp  from '../apps/PageEditApp.vue'
import VueFormGenerator from "vue3-form-generator-legacy";
import * as uiv from "uiv";
import VueCookies from "vue-cookies";
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
app.component('PageEditApp', PageEditApp);

app.mount( '#page-edit-app');