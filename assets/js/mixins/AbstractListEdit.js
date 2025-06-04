import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
import VueTables from 'vue-tables-2';
import { defineAsyncComponent } from 'vue';
import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue'
import Alerts from '../Components/Alerts.vue'
import EditListRow from '../Components/Edit/EditListRow.vue'
import Panel from '../Components/Edit/Panel.vue'
import axios from 'axios';
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
            this.openRequests++
            // get all dependencies
            axios.all(Object.values(this.depUrls).map(depUrlCat => axios.get(depUrlCat.depUrl)))
                .then((results) => {
                    this.delDependencies = {}
                    let dependencyCategories = Object.keys(this.depUrls)
                    for (let dependencyCategoryIndex of Object.keys(dependencyCategories)) {
                        if (results[dependencyCategoryIndex].data.length > 0) {
                            let dependencyCategory = dependencyCategories[dependencyCategoryIndex]
                            this.delDependencies[dependencyCategory] = {}
                            this.delDependencies[dependencyCategory].list = results[dependencyCategoryIndex].data
                            if (this.depUrls[dependencyCategory].url) {
                                this.delDependencies[dependencyCategory].url = this.depUrls[dependencyCategory].url
                            }
                            if (this.depUrls[dependencyCategory].urlIdentifier) {
                                this.delDependencies[dependencyCategory].urlIdentifier = this.depUrls[dependencyCategory].urlIdentifier
                            }
                        }
                    }
                    this.deleteModal = true
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while checking for dependencies.', login: this.isLoginError(error)})
                    console.log(error)
                })
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
        isLoginError(error) {
            return error.message === 'Network Error'
        },
        isOrIsChild(valueFromList, value) {
            if (value == null) {
                return false
            }
            if (valueFromList.id === value.id) {
                return true
            }
            if (valueFromList.parent != null) {
                return (this.isOrIsChild(this.values.filter((value) => value.id === valueFromList.parent.id)[0], value))
            }
            return false
        },
    },
}
