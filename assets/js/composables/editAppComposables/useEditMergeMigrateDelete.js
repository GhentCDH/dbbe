import { ref, reactive } from 'vue';
import axios from 'axios';
import { isLoginError } from '@/helpers/errorUtil';


export function useEditMergeMigrateDelete(initUrls = '{}', initData = '{}', depUrls = {}) {
    const urls = reactive(JSON.parse(initUrls));
    const valuesRaw = JSON.parse(initData);
    const values = Array.isArray(valuesRaw) ? ref(valuesRaw) : reactive(valuesRaw);

    const alerts = ref([]);
    const editAlerts = ref([]);
    const mergeAlerts = ref([]);
    const migrateAlerts = ref([]);
    const deleteAlerts = ref([]);

    const delDependencies = ref({});

    const deleteModal = ref(null);
    const editModalValue = ref(null);
    const mergeModal = ref(null);
    const migrateModal = ref(null);

    const originalMergeModel = reactive({});
    const originalMigrateModel = reactive({});
    const originalSubmitModel = reactive({});

    const openRequests = ref(0);

    function resetEdit(submitModel) {
        Object.assign(submitModel, JSON.parse(JSON.stringify(originalSubmitModel)));
    }
    function resetMerge() {
        Object.assign(originalMergeModel, JSON.parse(JSON.stringify(originalMergeModel)));
    }
    function resetMigrate() {
        Object.assign(originalMigrateModel, JSON.parse(JSON.stringify(originalMigrateModel)));
    }

    function deleteDependencies() {
        openRequests.value++;
        const depUrlsToUse = depUrls.value || depUrls;
        const depUrlsEntries = Object.entries(depUrlsToUse);
        axios
            .all(depUrlsEntries.map(([_, depUrlCat]) => axios.get(depUrlCat.depUrl)))
            .then(results => {
                delDependencies.value = {};  // ← Change: clear by reassigning
                results.forEach((response, index) => {
                    const data = response.data;
                    if (data.length > 0) {
                        const [category, depUrlCat] = depUrlsEntries[index];
                        delDependencies.value[category] = {  // ← Change: add .value
                            list: data,
                            ...(depUrlCat.url && { url: depUrlCat.url }),
                            ...(depUrlCat.urlIdentifier && { urlIdentifier: depUrlCat.urlIdentifier }),
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
                    login: isLoginError(error),
                });
                console.error(error);
            });
    }
    function cancelEdit() {
        editModalValue.value = null;
        editAlerts.value = [];
    }
    function cancelMerge() {
        mergeModal.value = null;
        mergeAlerts.value = [];
    }
    function cancelMigrate() {
        migrateModal.value = null;
        migrateAlerts.value = [];
    }
    function cancelDelete() {
        deleteModal.value = null;
        deleteAlerts.value = [];
    }
    function isOrIsChild(valueFromList, value, visited = new Set()) {
        if (!value || !valueFromList) return false;
        if (valueFromList.id === value.id) return true;
        if (visited.has(valueFromList.id)) return false;
        visited.add(valueFromList.id);
        const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {});
        const parent = safeValues.find(v => v.id === valueFromList.parent?.id);
        return parent ? isOrIsChild(parent, value, visited) : false;
    }

    return {
        urls,
        values,
        alerts,
        editAlerts,
        mergeAlerts,
        migrateAlerts,
        deleteAlerts,
        delDependencies,
        deleteModal,
        editModalValue,
        mergeModal,
        migrateModal,
        originalMergeModel,
        originalMigrateModel,
        originalSubmitModel,
        openRequests,
        resetEdit,
        resetMerge,
        resetMigrate,
        deleteDependencies,
        cancelEdit,
        cancelMerge,
        cancelMigrate,
        cancelDelete,
        isOrIsChild,
    };
}