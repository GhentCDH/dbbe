// composables/useSaveModel.js
import axios from 'axios'
import { isLoginError, getErrorMessage } from '@/helpers/errorUtil'
import { ref } from 'vue'
export function useSaveModel({ diff, urls, modelName }) {
    const openRequests = ref(0)
    const saveAlerts = ref([])
    const saveModal = ref(false)

    const toSave = () => {
        const result = {}
        for (const item of diff.value) {
            if ('keyGroup' in item) {
                result[item.keyGroup] ||= {}
                result[item.keyGroup][item.key] = item.value
            } else {
                result[item.key] = item.value
            }
        }
        return result
    }

    const save = (existingRecord) => {
        openRequests.value++
        saveModal.value = false

        const url = urls[modelName]
        const itemsToSave= toSave()
        console.log(itemsToSave)
        console.log(url)

        const request = existingRecord
            ? axios.put(url, itemsToSave)
            : axios.post(url, itemsToSave)
        request
            .then((response) => {
                console.log(response)
                window.onbeforeunload = null
                window.location = urls[`${modelName}_get`]
                    .replace(`${modelName}_id`, response.data.id)

            })
            .catch((error) => {
                console.log(error)
                saveModal.value = true
                saveAlerts.value.push({
                    type: 'error',
                    message: `Something went wrong while saving the ${modelName.replace('_', ' ')}.`,
                    extra: getErrorMessage(error),
                    login: isLoginError(error),
                })
                openRequests.value--
            })
    }

    return {
        save,
        saveModal,
        saveAlerts,
        openRequests,
        toSave,
    }
}
