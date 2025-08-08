import { ref } from 'vue'

export function useModelDiff(panelRefs, panelKeys) {
    const diff = ref([])

    const calcDiff = () => {
        diff.value = []
        for (let key of panelKeys) {
            const refItem = panelRefs.value[key]
            if (refItem) {
                diff.value = diff.value.concat(refItem.changes)
            }
        }

        if (diff.value.length > 0) {
            window.onbeforeunload = (e) => {
                const msg = 'There are unsaved changes.'
                e.returnValue = msg
                return msg
            }
        } else {
            window.onbeforeunload = null
        }
    }

    return {
        diff,
        calcDiff,
    }
}
