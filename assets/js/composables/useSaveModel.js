// composables/useSaveModel.js
import axios from 'axios'
import { isLoginError, getErrorMessage } from '@/helpers/errorUtil'
import { ref } from 'vue'
export function useSaveModel(urls ) {

    const openRequests = ref(0)
    const saveAlerts = ref([])
    const saveModal = ref(false)

    const postUpdatedModel = (modelType, itemsToSave) => {
        axios.post(urls[$`${modelType}_post`], itemsToSave)
            .then((response) => {
                window.onbeforeunload = function () {}
                window.location = urls[`${modelType}_get`].replace(`${modelType}_id`, response.data.id)
            })
            .catch((error) => {
                console.log(error)
                saveModal.value = true
                saveAlerts.value.push({
                    type: 'error',
                    message: 'Something went wrong while saving the blog post data.',
                    extra: getErrorMessage(error),
                    login: isLoginError(error)
                })
                openRequests.value--
            })
    }

    const putUpdatedModel = (modelType, itemsToSave) => {
        console.log(urls[`${modelType}_put`], 'urls',urls)
        axios.put(urls[`${modelType}_put`], itemsToSave)
            .then((response) => {
                window.onbeforeunload = function () {}
                window.location = urls[`${modelType}_get`]
            })
            .catch((error) => {
                console.log(error)
                saveModal.value = true
                saveAlerts.value.push({
                    type: 'error',
                    message: 'Something went wrong while saving the blog post data.',
                    extra: getErrorMessage(error),
                    login: isLoginError(error)
                })
                openRequests.value--
            })
    }

    return {
        saveModal,
        saveAlerts,
        openRequests,
        postUpdatedModel,
        putUpdatedModel

    }
}
