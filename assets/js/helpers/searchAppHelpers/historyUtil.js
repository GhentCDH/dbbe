import qs from 'qs';
import {changeMode} from "@/helpers/formatUtil";

export function pushHistory(data, model, originalModel, fields, defaultOrderBy = 'incipit', defaultPerPage = 25) {
    const filteredData = JSON.parse(JSON.stringify(data));

    if ('limit' in filteredData && filteredData.limit === defaultPerPage) {
        delete filteredData.limit;
    }
    if ('page' in filteredData && filteredData.page === 1) {
        delete filteredData.page;
    }
    if (
        'orderBy' in filteredData &&
        filteredData.orderBy === defaultOrderBy &&
        'ascending' in filteredData &&
        filteredData.ascending === 1
    ) {
        delete filteredData.orderBy;
        delete filteredData.ascending;
    }

    if ('filters' in filteredData) {
        for (const fieldName of Object.keys(fields)) {
            if (fieldName in filteredData.filters) {
                const field = fields[fieldName];
                if (fieldName in originalModel) {
                    if (model[fieldName] === originalModel[fieldName]) {
                        delete filteredData.filters[fieldName];
                    }
                }
                if (field.multiDependency != null) {
                    if (model[field.multiDependency] == null || model[field.multiDependency].length < 2) {
                        delete filteredData.filters[fieldName];
                    }
                }
            }
        }
    }

    window.history.pushState(
        filteredData,
        document.title,
        `${document.location.href.split('?')[0]}?${qs.stringify(filteredData)}`
    );
}

export function popHistory() {
    if (window.location.href.split('?', 2).length > 1) {
        return window.location.href.split('?', 2)[1];
    }
    return 'init';
}


export function buildHistoryValues(model, fields) {
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
            if (fieldValue != null && !Number.isNaN(fieldValue) && fieldValue !== '') {
                if (!result.date) result.date = {};
                result.date[fieldName === 'year_from' ? 'from' : 'to'] = fieldValue;
            }
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