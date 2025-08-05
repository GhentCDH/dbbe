import { ref, reactive, nextTick } from 'vue'
import axios from 'axios'
import { isLoginError } from "@/helpers/errorUtil"

export function useEntityEdit(props) {
    // Reactive state
    const urls = ref(JSON.parse(props.initUrls))
    const data = ref(JSON.parse(props.initData))

    const formOptions = ref({
        validateAfterChanged: true,
        validationErrorClass: "has-error",
        validationSuccessClass: "success"
    })

    const openRequests = ref(0)
    const alerts = ref([])
    const saveAlerts = ref([])
    const originalModel = ref({})
    const diff = ref([])
    const resetModal = ref(false)
    const invalidModal = ref(false)
    const saveModal = ref(false)
    const invalidPanels = ref(false)
    const scrollY = ref(null)
    const isSticky = ref(false)
    const stickyStyle = ref({})
    const reloads = ref([])

    // Methods
    const initScroll = (anchorRef) => {
        window.addEventListener('scroll', () => {
            scrollY.value = Math.round(window.scrollY)
        })
    }


    const validateForms = (panelRefs, panels) => {
        for (let panel of panels) {
            if (panelRefs[panel] && panelRefs[panel].validate) {
                panelRefs[panel].validate()
            }
        }
    }

    const calcAllChanges = (panelRefs, panels) => {
        for (let panel of panels) {
            if (panelRefs[panel] && panelRefs[panel].calcChanges) {
                panelRefs[panel].calcChanges()
            }
        }
    }

    const validated = (panelRefs, panels) => {
        invalidPanels.value = false
        for (let panel of panels) {
            if (panelRefs[panel] && !panelRefs[panel].isValid) {
                invalidPanels.value = true
                break
            }
        }
        calcDiff(panelRefs, panels)
    }

    const calcDiff = (panelRefs, panels) => {
        diff.value = []
        for (let panel of panels) {
            if (panelRefs[panel] && panelRefs[panel].changes) {
                diff.value = diff.value.concat(panelRefs[panel].changes)
            }
        }

        if (diff.value.length !== 0) {
            window.onbeforeunload = function(e) {
                let dialogText = 'There are unsaved changes.'
                e.returnValue = dialogText
                return dialogText
            }
        }
    }

    const toSave = () => {
        let result = {}
        for (let diffItem of diff.value) {
            if ('keyGroup' in diffItem) {
                if (!(diffItem.keyGroup in result)) {
                    result[diffItem.keyGroup] = {}
                }
                result[diffItem.keyGroup][diffItem.key] = diffItem.value
            } else {
                result[diffItem.key] = diffItem.value
            }
        }
        return result
    }

    const reset = (model, panelRefs, panels) => {
        resetModal.value = false
        // This needs to be handled in the component since model is component-specific
        Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
        nextTick(() => {
            validateForms(panelRefs, panels)
        })
    }

    const saveButton = (panelRefs, panels) => {
        validateForms(panelRefs, panels)
        if (invalidPanels.value) {
            invalidModal.value = true
        } else {
            saveModal.value = true
        }
    }

    const cancelSave = () => {
        saveModal.value = false
        saveAlerts.value = []
    }

    const reloadSimpleItems = (type) => {
        reloadItems(
            type,
            [type],
            [data.value[type]],
            urls.value[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
        )
    }

    // parent can either be an array of multiple parents or a single parent
    const reloadNestedItems = (type, parent) => {
        reloadItems(
            type,
            [type],
            Array.isArray(parent) ? parent.map(p => p[type]) : [parent[type]],
            urls.value[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
        )
    }

    const reloadItems = (type, keys, items, url, filters, panelRefs, panels) => {
        // Be careful to mutate the existing array and not create a new one
        if (panelRefs && panels) {
            for (let panel of panels) {
                if (panelRefs[panel] && panelRefs[panel].disableFields) {
                    panelRefs[panel].disableFields(keys)
                }
            }
        }
        reloads.value.push(type)

        axios.get(url)
            .then((response) => {
                for (let i = 0; i < items.length; i++) {
                    let responseData = []
                    if (filters == null || filters[i] == null) {
                        // Copy data
                        responseData = response.data.filter(() => true)
                    } else {
                        responseData = response.data.filter(filters[i])
                    }
                    while (items[i].length) {
                        items[i].splice(0, 1)
                    }
                    while (responseData.length) {
                        items[i].push(responseData.shift())
                    }
                }

                if (panelRefs && panels) {
                    for (let panel of panels) {

                        if (panelRefs[panel] && panelRefs[panel].enableFields) {

                            panelRefs[panel].enableFields(keys)
                        }
                    }
                }

                let typeIndex = reloads.value.indexOf(type)
                if (typeIndex > -1) {
                    reloads.value.splice(typeIndex, 1)
                }
            })
            .catch((error) => {
                alerts.value.push({
                    type: 'error',
                    message: 'Something went wrong while loading data.',
                    login: isLoginError(error)
                })

                console.log(error)
            })
    }

    return {
        // Reactive refs
        urls,
        data,
        formOptions,
        openRequests,
        alerts,
        saveAlerts,
        originalModel,
        diff,
        resetModal,
        invalidModal,
        saveModal,
        invalidPanels,
        scrollY,
        isSticky,
        stickyStyle,
        reloads,

        // Methods
        initScroll,
        validateForms,
        calcAllChanges,
        validated,
        calcDiff,
        toSave,
        reset,
        saveButton,
        cancelSave,
        reloadSimpleItems,
        reloadNestedItems,
        reloadItems
    }
}