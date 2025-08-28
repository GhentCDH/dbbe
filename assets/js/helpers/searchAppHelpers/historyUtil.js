import qs from 'qs';

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