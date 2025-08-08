// composables/useCollectionManagement.js
import { ref, computed } from 'vue';
import axios from 'axios';

export function useCollectionManagement({
                                            data,
                                            urls,
                                            constructFilterValues,
                                            resultTableRef,
                                            alerts,
                                            startRequest,
                                            endRequest,
                                            noHistory
                                        }) {
    const collectionArray = ref([]);

    const allRowsSelected = computed(() =>
        data.data?.every(row => collectionArray.value.includes(row.id))
    );

    function collectionToggleAll() {
        if (!data || !data.data) return;
        if (allRowsSelected.value) {
            clearCollection();
        } else {
            for (const row of data.data) {
                if (!collectionArray.value.includes(row.id)) {
                    collectionArray.value.push(row.id);
                }
            }
        }
    }

    function clearCollection() {
        collectionArray.value = [];
    }

    function addManagementsToSelection(managementCollections) {
        updateManagements('add', 'selection', managementCollections);
    }

    function removeManagementsFromSelection(managementCollections) {
        updateManagements('remove', 'selection', managementCollections);
    }

    function addManagementsToResults(managementCollections) {
        updateManagements('add', 'results', managementCollections);
    }

    function removeManagementsFromResults(managementCollections) {
        updateManagements('remove', 'results', managementCollections);
    }

    function updateManagements(action, target, managementCollections) {
        const url = urls?.[`managements_${action}`];
        if (!url) return;

        const payload =
            target === 'selection'
                ? { ids: collectionArray.value, managements: managementCollections }
                : { filter: constructFilterValues(), managements: managementCollections };

        const messages = {
            add: {
                success: 'Management collections added successfully.',
                error: 'Something went wrong while adding the management collections.',
            },
            remove: {
                success: 'Management collections removed successfully.',
                error: 'Something went wrong while removing the management collections.',
            },
        };

        startRequest();

        axios
            .put(url, payload)
            .then(() => {
                noHistory.value = true;
                resultTableRef.value?.refresh();
                alerts.value.push({
                    type: 'success',
                    message: messages[action].success,
                });
            })
            .catch((error) => {
                alerts.value.push({
                    type: 'error',
                    message: messages[action].error,
                });
                console.error(error);
            })
            .finally(() => {
                endRequest();
            });
    }

    return {
        collectionArray,
        allRowsSelected,
        collectionToggleAll,
        clearCollection,
        addManagementsToSelection,
        removeManagementsFromSelection,
        addManagementsToResults,
        removeManagementsFromResults,
    };
}
