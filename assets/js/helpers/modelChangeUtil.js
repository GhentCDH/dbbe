export function calcChanges(model, originalModel, fields) {
    const changes = [];
    if (!originalModel) return changes;

    const dateFields = ['lastAccessed', 'postDate'];

    for (const key of Object.keys(model)) {
        if (
            JSON.stringify(model[key]) !== JSON.stringify(originalModel[key]) &&
            !(model[key] == null && originalModel[key] == null)
        ) {
            let value = model[key];

            if (dateFields.includes(key)) {
                if (model[key] == null || model[key] === '') {
                    value = null;
                } else {
                    value = model[key].substr(6, 4) + '-' + model[key].substr(3, 2) + '-' + model[key].substr(0, 2);
                }
            }

            changes.push({
                key,
                label: fields[key]?.label || key,
                old: originalModel[key],
                new: model[key],
                value: value,
            });
        }
    }
    return changes;
}