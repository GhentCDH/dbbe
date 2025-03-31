import PersonEditApp  from '../apps/PersonEditApp.vue'
import {createApp} from "vue";
import VueFormGenerator from "vue3-form-generator-legacy";
import * as uiv from "uiv";
import VueCookies from "vue-cookies";
import fieldRadio from "../Components/FormFields/fieldRadio.vue";
import Alerts from "../Components/Alerts.vue";
import Delete from "../Components/Edit/Modals/Delete.vue";
import CollectionManager from "../Components/Search/CollectionManager.vue";
import fieldMultiselectClear from "../Components/FormFields/fieldMultiselectClear.vue";
const app=createApp();

const panelComponents = import.meta.glob('/Components/Edit/Panels/*.vue');

for (const path in panelComponents) {
    let compName = path.match(/\/(BasicPerson|Date|Identification|Office|Bibliography|GeneralPerson|Management)\.vue$/)?.[1];

    if (compName) {
        panelComponents[path]().then((mod) => {
            app.component(
                compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel',
                mod.default
            );
        });
    }
}
app.config.compilerOptions.whitespace='condense';
app.use(VueFormGenerator)
app.use(uiv)
app.use(VueCookies);
app.component('FieldRadio', fieldRadio);
app.component('FieldMultiselectClear', fieldMultiselectClear);
app.component('Alerts', Alerts);
app.component('DeleteModal', Delete);
app.component('CollectionManager', CollectionManager);
app.component('PersonEditApp', PersonEditApp);

app.mount( '#person-edit-app');