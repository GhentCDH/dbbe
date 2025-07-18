import { changeMode } from '../formatUtil'; // import dependencies explicitly

export function constructFilterValues(model, fields) {
    const result = {};
    if (model == null) return result;
    for (const fieldName of Object.keys(model)) {
        const fieldValue = model[fieldName];
        const fieldDef = fields[fieldName];
        if (fieldDef?.type === 'multiselectClear' && fieldValue != null) {
            if (Array.isArray(fieldValue)) {
                result[fieldName] = fieldValue.map(v => v.id);
            } else {
                result[fieldName] = fieldValue.id;
            }
            continue;
        }
        if (fieldName === 'year_from' || fieldName === 'year_to') {
            if (!result.date) result.date = {};
            result.date[fieldName === 'year_from' ? 'from' : 'to'] = fieldValue;
            continue;
        }

        const modeField = `${fieldName}_mode`;
        if (modeField in model) {
            if (model[modeField]?.[0] === 'betacode') {
                result[fieldName] = changeMode('betacode', 'greek', fieldValue.trim());
            } else {
                result[fieldName] = fieldValue.trim();
            }
            continue;
        }
        if (Array.isArray(fieldValue)) {
            result[fieldName] = fieldValue[0];
        } else {
            result[fieldName] = fieldValue;
        }
    }

    return result;
}
