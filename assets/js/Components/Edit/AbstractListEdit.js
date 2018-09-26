window.axios = require('axios')

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Alerts from '../Alerts'
import EditListRow from './EditListRow'
import Panel from './Panel'

Vue.use(VueFormGenerator)
Vue.use(uiv)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
Vue.component('editListRow', EditListRow)
Vue.component('panel', Panel)

const modalComponents = require.context('./Modals', false, /[/](?:Edit|Merge|Migrate|Delete)[.]vue$/)
for(let key of modalComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Modal', modalComponents(key).default)
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
        // depUrls format: {
        //   CategoryName: {
        //     depUrl: (link to check for dependencies)
        //     url: (can be used to link the specific dependency)
        //     urlIdentifier: (can be used to link the specific dependency)
        //   }
        // }
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
