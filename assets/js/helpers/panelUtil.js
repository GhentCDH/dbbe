
export const disablePanels = (panelRefs, panels, keys) => {
    if (!panelRefs || !panels) return
    for (const panel of panels) {
        const panelRef = panelRefs.value[panel]
        if (panelRef?.disableFields) {
            panelRef.disableFields(keys)
        }
    }
}

export const enablePanels = (panelRefs, panels, keys) => {
    if (!panelRefs || !panels) return
    for (const panel of panels) {
        const panelRef = panelRefs.value[panel]
        if (panelRef?.enableFields) {
            panelRef.enableFields(keys)
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
