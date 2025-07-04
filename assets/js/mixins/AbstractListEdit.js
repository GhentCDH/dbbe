import Vue from 'vue';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
import VueTables from 'vue-tables-2';
import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../Components/Alerts.vue'
import EditListRow from '../Components/Edit/EditListRow.vue'
import Panel from '../Components/Edit/Panel.vue'
import axios from 'axios';
import {isLoginError} from "@/helpers/errorUtil";
import { defineAsyncComponent } from 'vue';
window.axios = axios;

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(VueTables.ServerTable);
Vue.use(VueFormGenerator)
Vue.use(uiv)
Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
Vue.component('editListRow', EditListRow)
Vue.component('panel', Panel)
const modalComponents = import.meta.glob('./Modals/*{Edit,Merge,Migrate,Delete}.vue');

for (let path in modalComponents) {
    let compName = path.replace(/^\.\//, '').replace(/\.vue$/, '').split('/').pop();
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Modal', defineAsyncComponent(modalComponents[path]));
}
export default {
    props: {
        initUrls: {
            type: String,
            default: '',
        },
        initData: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            urls: JSON.parse(this.initUrls),
            values: JSON.parse(this.initData),
            alerts: [],
            editAlerts: [],
            mergeAlerts: [],
            migrateAlerts: [],
            deleteAlerts: [],
            delDependencies: {},
            deleteModal: false,
            editModal: false,
            mergeModal: false,
            migrateModal: false,
            originalMergeModel: {},
            originalMigrateModel: {},
            originalSubmitModel: {},
            openRequests: 0,
        }
    },
    methods: {
        resetEdit() {
            this.submitModel = JSON.parse(JSON.stringify(this.originalSubmitModel))
        },
        resetMerge() {
            this.mergeModel = JSON.parse(JSON.stringify(this.originalMergeModel))
        },
        resetMigrate() {
            this.migrateModel = JSON.parse(JSON.stringify(this.originalMigrateModel))
        },
        deleteDependencies() {
            this.openRequests++;
            const depUrlsEntries = Object.entries(this.depUrls);

            axios.all(depUrlsEntries.map(([_, depUrlCat]) => axios.get(depUrlCat.depUrl)))
                .then(results => {
                    this.delDependencies = {};

                    results.forEach((response, index) => {
                        const data = response.data;
                        if (data.length > 0) {
                            const [category, depUrlCat] = depUrlsEntries[index];
                            this.delDependencies[category] = {
                                list: data,
                                ...(depUrlCat.url && { url: depUrlCat.url }),
                                ...(depUrlCat.urlIdentifier && { urlIdentifier: depUrlCat.urlIdentifier }),
                            };
                        }
                    });

                    this.deleteModal = true;
                    this.openRequests--;
                })
                .catch(error => {
                    this.openRequests--;
                    this.alerts.push({
                        type: 'error',
                        message: 'Something went wrong while checking for dependencies.',
                        login: isLoginError(error),
                    });
                    console.error(error);
                });
        },
        cancelEdit() {
            this.editModal = false
            this.editAlerts = []
        },
        cancelMerge() {
            this.mergeModal = false
            this.mergeAlerts = []
        },
        cancelMigrate() {
            this.migrateModal = false
            this.migrateAlerts = []
        },
        cancelDelete() {
            this.deleteModal = false
            this.deleteAlerts = []
        },
        isOrIsChild(valueFromList, value) {
            if (!value || !valueFromList) return false;
            if (valueFromList.id === value.id) return true;

            const parent = this.values.find(v => v.id === valueFromList.parent?.id);
            return parent ? this.isOrIsChild(parent, value) : false;
        }
    },
}
