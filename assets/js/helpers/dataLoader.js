import {isLoginError} from "@/helpers/errorUtil";

export const disablePanels = (panelRefs, panels, keys) => {
    if (!panelRefs || !panels) return
    for (const panel of panels) {
        const panelRef = panelRefs[panel]
        if (panelRef?.value?.disableFields) {
            panelRef.value.disableFields(keys)
        }
    }
}

export const enablePanels = (panelRefs, panels, keys) => {
    if (!panelRefs || !panels) return
    for (const panel of panels) {
        const panelRef = panelRefs[panel]
        if (panelRef?.value?.enableFields) {
            panelRef.value.enableFields(keys)
        }
    }
}

export const updateItems = (items, data, filters) => {
    for (let i = 0; i < items.length; i++) {
        const filteredData = filters?.[i]
            ? data.filter(filters[i])
            : data.slice()

        items[i].splice(0, items[i].length, ...filteredData)
    }
}

export const reloadItems = (type, keys, items, url, filters, panelRefs, panels, reloadsRef, alertsRef) => {
    disablePanels(panelRefs, panels, keys)
    console.log(reloadsRef,'here')
    reloadsRef.value.push(type)

    axios.get(url)
        .then(response => {
            updateItems(items, response.data, filters)
            enablePanels(panelRefs, panels, keys)
            const index = reloadsRef.value.indexOf(type)
            if (index > -1) reloadsRef.value.splice(index, 1)
        })
        .catch(error => {
            alertsRef.value.push({
                type: 'error',
                message: 'Something went wrong while loading data.',
                login: isLoginError(error),
            })
            console.error(error)
        })
}
