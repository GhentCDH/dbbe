export function calcChanges(model, originalModel, fields) {
    const changes = [];
    if (!originalModel) return changes;

    for (const key of Object.keys(model)) {
        if (
            JSON.stringify(model[key]) !== JSON.stringify(originalModel[key]) &&
            !(model[key] == null && originalModel[key] == null)
        ) {
            changes.push({
                key,
                label: fields[key]?.label || key,
                old: originalModel[key],
                new: model[key],
                value: model[key],
            });
        }
    }
    return changes;
}
