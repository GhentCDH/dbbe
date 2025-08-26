import { ref, computed } from 'vue'

export function usePanelValidation(panelRefs, panelKeys) {
    const invalidPanels = ref(false)

    const validateForms = () => {
        for (let key of panelKeys) {
            const refItem = panelRefs.value[key]
            if (refItem) refItem.validate()
        }
    }

    const checkInvalidPanels = () => {
        invalidPanels.value = false
        for (let key of panelKeys) {
            const refItem = panelRefs.value[key]
            if (refItem && !refItem.isValid) {
                invalidPanels.value = true
                break
            }
        }
    }

    return {
        invalidPanels,
        validateForms,
        checkInvalidPanels,
    }
}
