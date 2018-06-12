window.axios = require('axios')

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import * as uiv from 'uiv'
import VueMultiselect from 'vue-multiselect'

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Alerts from '../Alerts'
import EditListRow from './EditListRow'
import Panel from './Panel'

const modalComponents = require.context('./Modals', false, /[.]vue$/)

Vue.use(VueFormGenerator)
Vue.use(uiv)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)
Vue.component('editListRow', EditListRow)
Vue.component('panel', Panel)

for(let key of modalComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    if (['Edit', 'Merge', 'Delete'].includes(compName)) {
        Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Modal', modalComponents(key).default)
    }
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
            delDependencies: {},
            deleteModal: false,
            editModal: false,
            mergeModal: false,
            originalMergeModel: {},
            openRequests: 0,
            originalSubmitModel: {},
        }
    },
    methods: {
        resetEdit() {
            this.submitModel = JSON.parse(JSON.stringify(this.originalSubmitModel))
        },
        resetMerge() {
            this.mergeModel = JSON.parse(JSON.stringify(this.originalMergeModel))
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
                    this.alerts.push({type: 'error', message: 'Something whent wrong while checking for dependencies.'})
                    console.log(error)
                })
        }
    },
}
