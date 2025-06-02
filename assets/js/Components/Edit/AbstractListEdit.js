import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'
import VueTables from 'vue-tables-2';
import { defineAsyncComponent } from 'vue';
import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Alerts from '../Alerts'
import EditListRow from './EditListRow'
import Panel from './Panel'
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
//
// export default {
//     props: {
//         initUrls: {
//             type: String,
//             default: '',
//         },
//         initData: {
//             type: String,
//             default: '',
//         },
//     },
//     data() {
//         return {
//             urls: JSON.parse(this.initUrls),
//             values: JSON.parse(this.initData),
//             alerts: [],
//             editAlerts: [],
//             mergeAlerts: [],
//             migrateAlerts: [],
//             deleteAlerts: [],
//             delDependencies: {},
//             deleteModal: false,
//             editModal: false,
//             mergeModal: false,
//             migrateModal: false,
//             originalMergeModel: {},
//             originalMigrateModel: {},
//             originalSubmitModel: {},
//             openRequests: 0,
//         }
//     },
//     methods: {
//         resetEdit() {
//             this.submitModel = JSON.parse(JSON.stringify(this.originalSubmitModel))
//         },
//         resetMerge() {
//             this.mergeModel = JSON.parse(JSON.stringify(this.originalMergeModel))
//         },
//         resetMigrate() {
//             this.migrateModel = JSON.parse(JSON.stringify(this.originalMigrateModel))
//         },
//         deleteDependencies() {
//             this.openRequests++
//             // get all dependencies
//             axios.all(Object.values(this.depUrls).map(depUrlCat => axios.get(depUrlCat.depUrl)))
//                 .then((results) => {
//                     this.delDependencies = {}
//                     let dependencyCategories = Object.keys(this.depUrls)
//                     for (let dependencyCategoryIndex of Object.keys(dependencyCategories)) {
//                         if (results[dependencyCategoryIndex].data.length > 0) {
//                             let dependencyCategory = dependencyCategories[dependencyCategoryIndex]
//                             this.delDependencies[dependencyCategory] = {}
//                             this.delDependencies[dependencyCategory].list = results[dependencyCategoryIndex].data
//                             if (this.depUrls[dependencyCategory].url) {
//                                 this.delDependencies[dependencyCategory].url = this.depUrls[dependencyCategory].url
//                             }
//                             if (this.depUrls[dependencyCategory].urlIdentifier) {
//                                 this.delDependencies[dependencyCategory].urlIdentifier = this.depUrls[dependencyCategory].urlIdentifier
//                             }
//                         }
//                     }
//                     this.deleteModal = true
//                     this.openRequests--
//                 })
//                 .catch( (error) => {
//                     this.openRequests--
//                     this.alerts.push({type: 'error', message: 'Something went wrong while checking for dependencies.', login: this.isLoginError(error)})
//                     console.log(error)
//                 })
//         },
//         cancelEdit() {
//             this.editModal = false
//             this.editAlerts = []
//         },
//         cancelMerge() {
//             this.mergeModal = false
//             this.mergeAlerts = []
//         },
//         cancelMigrate() {
//             this.migrateModal = false
//             this.migrateAlerts = []
//         },
//         cancelDelete() {
//             this.deleteModal = false
//             this.deleteAlerts = []
//         },
//         isLoginError(error) {
//             return error.message === 'Network Error'
//         },
//         isOrIsChild(valueFromList, value) {
//             if (value == null) {
//                 return false
//             }
//             if (valueFromList.id === value.id) {
//                 return true
//             }
//             if (valueFromList.parent != null) {
//                 return (this.isOrIsChild(this.values.filter((value) => value.id === valueFromList.parent.id)[0], value))
//             }
//             return false
//         },
//     },
// }

// useMyFeature.js
import { ref, reactive, computed } from 'vue'

export function useListEdit(initUrls = '{}', initData = '{}') {
    const urls = reactive(JSON.parse(initUrls))
    const values = reactive(JSON.parse(initData))

    const alerts = ref([])
    const editAlerts = ref([])
    const mergeAlerts = ref([])
    const migrateAlerts = ref([])
    const deleteAlerts = ref([])

    const delDependencies = reactive({})
    const deleteModal = ref(false)
    const editModal = ref(false)
    const mergeModal = ref(false)
    const migrateModal = ref(false)

    const originalMergeModel = reactive({})
    const originalMigrateModel = reactive({})
    const originalSubmitModel = reactive({})

    const openRequests = ref(0)

    function resetEdit() {
        Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(originalSubmitModel)))
    }
    function resetMerge() {
        Object.assign(originalMergeModel, JSON.parse(JSON.stringify(originalMergeModel)))
    }
    function resetMigrate() {
        Object.assign(originalMigrateModel, JSON.parse(JSON.stringify(originalMigrateModel)))
    }

    function deleteDependencies() {
        openRequests.value++
        // get all dependencies
        axios.all(Object.values(urls).map(depUrlCat => axios.get(depUrlCat.depUrl)))
            .then(results => {
                for (let i = 0; i < results.length; i++) {
                    const dependencyCategory = Object.keys(urls)[i]
                    if (results[i].data.length > 0) {
                        delDependencies[dependencyCategory] = {}
                        delDependencies[dependencyCategory].list = results[i].data
                        if (urls[dependencyCategory].url) {
                            delDependencies[dependencyCategory].url = urls[dependencyCategory].url
                        }
                        if (urls[dependencyCategory].urlIdentifier) {
                            delDependencies[dependencyCategory].urlIdentifier = urls[dependencyCategory].urlIdentifier
                        }
                    }
                }
                deleteModal.value = true
                openRequests.value--
            })
            .catch(error => {
                openRequests.value--
                alerts.value.push({ type: 'error', message: 'Something went wrong while checking for dependencies.', login: isLoginError(error) })
                console.error(error)
            })
    }

    function cancelEdit() {
        editModal.value = false
        editAlerts.value = []
    }
    function cancelMerge() {
        mergeModal.value = false
        mergeAlerts.value = []
    }
    function cancelMigrate() {
        migrateModal.value = false
        migrateAlerts.value = []
    }
    function cancelDelete() {
        deleteModal.value = false
        deleteAlerts.value = []
    }

    function isLoginError(error) {
        return error.message === 'Network Error'
    }

    function isOrIsChild(valueFromList, value) {
        if (value == null) return false
        if (valueFromList.id === value.id) return true
        if (valueFromList.parent != null) {
            const parentValue = values.find(v => v.id === valueFromList.parent.id)
            return isOrIsChild(parentValue, value)
        }
        return false
    }

    return {
        urls, values,
        alerts, editAlerts, mergeAlerts, migrateAlerts, deleteAlerts,
        delDependencies,
        deleteModal, editModal, mergeModal, migrateModal,
        originalMergeModel, originalMigrateModel, originalSubmitModel,
        openRequests,
        resetEdit, resetMerge, resetMigrate,
        deleteDependencies,
        cancelEdit, cancelMerge, cancelMigrate, cancelDelete,
        isLoginError,
        isOrIsChild,
    }
}
