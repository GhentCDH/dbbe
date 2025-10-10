import { createApp } from 'vue'
import OccurrenceSearchApp from '../apps/OccurrenceSearchApp.vue'

// Components
import DeleteModal from '../components/Edit/Modals/Delete.vue'
import CollectionManager from '../components/Search/CollectionManager.vue'
import fieldRadio from '../components/FormFields/fieldRadio.vue'
import fieldMultiselectClear from '../components/FormFields/fieldMultiselectClear.vue'
import fieldCheckboxes from '@/components/FormFields/fieldCheckboxes.vue'
import Alerts from '../components/Alerts.vue'

import axios from 'axios'
import VueCookies from 'vue-cookies'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
window.axios = axios

const app = createApp({})

app.component('DeleteModal', DeleteModal)
app.component('CollectionManager', CollectionManager)
app.component('FieldRadio', fieldRadio)
app.component('FieldMultiselectClear', fieldMultiselectClear)
app.component('FieldCheckboxes', fieldCheckboxes)
app.component('Alerts', Alerts)
app.component('multiselect', VueMultiselect)
app.use(uiv)
app.use(VueCookies)
app.use(VueFormGenerator)

app.component('occurrence-search-app', OccurrenceSearchApp)
app.mount('#occurrence-search-app')
