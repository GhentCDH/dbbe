// Corrected Main Setup Script
import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator';
import VueMultiselect from 'vue-multiselect';
import * as uiv from 'uiv';
import { ServerTable } from 'vue-tables-2';
import { defineAsyncComponent } from 'vue';

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear';
import Alerts from '../Alerts';
import EditListRow from './EditListRow';
import Panel from './Panel';
import axios from 'axios';

window.axios = axios;

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(ServerTable);

Vue.component('multiselect', VueMultiselect);
Vue.component('fieldMultiselectClear', fieldMultiselectClear);
Vue.component('alerts', Alerts);
Vue.component('editListRow', EditListRow);
Vue.component('panel', Panel);

const modalComponents = import.meta.glob('./Modals/*{Edit,Merge,Migrate,Delete}.vue');
for (const path in modalComponents) {
    const compName = path.match(/\/([^/]+)\.vue$/)[1];
    const formattedName = compName.charAt(0).toLowerCase() + compName.slice(1) + 'Modal';
    Vue.component(formattedName, defineAsyncComponent(modalComponents[path]));
}

// useListEdit.js
import { ref, reactive } from 'vue';

export function useListEdit(initUrls = '{}', initData = '{}') {
    const urls = ref(JSON.parse(initUrls));
    const parsedData = JSON.parse(initData);
    const values = Array.isArray(parsedData) ? ref(parsedData) : reactive(parsedData);

    const alerts = ref([]);
    const editAlerts = ref([]);
    const mergeAlerts = ref([]);
    const migrateAlerts = ref([]);
    const deleteAlerts = ref([]);

    const delDependencies = reactive({});
    const deleteModal = ref(false);
    const editModal = ref(false);
    const mergeModal = ref(false);
    const migrateModal = ref(false);

    const originalMergeModel = reactive({});
    const originalMigrateModel = reactive({});
    const submitModel = reactive({
        submitType: '',
        management: null,
    })
    const originalSubmitModel = reactive({
        submitType: '',
        management: null,
    })


    const openRequests = ref(0);

    function resetEdit() {
        Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(originalSubmitModel)));
    }
    function resetMerge() {
        Object.assign(originalMergeModel, JSON.parse(JSON.stringify(originalMergeModel)));
    }
    function resetMigrate() {
        Object.assign(originalMigrateModel, JSON.parse(JSON.stringify(originalMigrateModel)));
    }

    function deleteDependencies() {
        openRequests.value++;
        const depKeys = Object.keys(urls.value).filter(k => k.endsWith('_get'));

        axios.all(depKeys.map(key => axios.get(urls.value[key])))
            .then(results => {
                const deleteKeyMap = {
                    managements: 'management_delete',
                    types: 'type_delete',
                    occurrences: 'occurrence_delete',
                };

                results.forEach((res, i) => {
                    const category = depKeys[i].replace('_get', '');
                    const data = res.data;
                    if (Array.isArray(data) && data.length > 0) {
                        delDependencies[category] = {
                            list: data,
                            url: urls.value[deleteKeyMap[category] || `${category}_delete`],
                            urlIdentifier: 'id'
                        };
                    }
                });
                deleteModal.value = true;
                openRequests.value--;
            })
            .catch(error => {
                openRequests.value--;
                alerts.value.push({
                    type: 'error',
                    message: 'Something went wrong while checking for dependencies.',
                    login: isLoginError(error)
                });
                console.error(error);
            });
    }

    function cancelEdit() { editModal.value = false; editAlerts.value = []; }
    function cancelMerge() { mergeModal.value = false; mergeAlerts.value = []; }
    function cancelMigrate() { migrateModal.value = false; migrateAlerts.value = []; }
    function cancelDelete() { deleteModal.value = false; deleteAlerts.value = []; }

    function isLoginError(error) { return error.message === 'Network Error'; }

    function isOrIsChild(valueFromList, value) {
        if (!value) return false;
        if (valueFromList.id === value.id) return true;
        if (valueFromList.parent) {
            const parentValue = values.value.find(v => v.id === valueFromList.parent.id);
            return isOrIsChild(parentValue, value);
        }
        return false;
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
        isLoginError, isOrIsChild,
    };
}
