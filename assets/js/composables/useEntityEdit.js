import { ref, reactive, nextTick } from 'vue'
import {reloadItems} from "@/helpers/dataLoader";

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