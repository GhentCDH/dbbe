import { changeMode } from '../formatUtil'; // import dependencies explicitly

export function buildFilterParams(model) {
    const filterParams = {};

    Object.entries(model).forEach(([key, value]) => {
        if (value != null && value !== '' && !(Array.isArray(value) && value.length === 0)) {

            if (key === 'year_from' || key === 'year_to') {
                const numericValue = Number(value);
                if (isNaN(numericValue) || !isFinite(numericValue)) return;

                if (!filterParams.date) filterParams.date = {};
                const dateKey = key === 'year_from' ? 'from' : 'to';
                filterParams.date[dateKey] = numericValue;
                return;
            }

            if (Array.isArray(value) && value.length > 0) {
                if (key.endsWith('_mode')) {
                    filterParams[key] = value[0];
                } else {
                    filterParams[key] = value.map(item =>
                        typeof item === 'object' && item.id ? item.id : item
                    );
                }
            } else if (typeof value === 'object' && value.id) {
                filterParams[key] = value.id;
            } else {
                filterParams[key] = value;
            }
        }
    });

    if (filterParams.date && Object.keys(filterParams.date).length === 0) {
        delete filterParams.date;
    }

    return filterParams;
}
