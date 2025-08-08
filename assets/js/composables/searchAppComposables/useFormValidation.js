import { ref } from 'vue';
import qs from 'qs';
import { constructFilterValues } from '@/helpers/searchAppHelpers/filterUtil';

export function useFormValidation({ model, fields, resultTableRef, defaultOrdering, emitFilter, historyRequest }) {
    const lastChangedField = ref('');
    const inputCancel = ref(null);
    const lastOrder = ref(null);
    const oldFilterValues = ref({});
    const actualRequest = ref(false);

    const clearInvalidYearFields = () => {
        let revalidateNeeded = false;
        ['year_from', 'year_to'].forEach((field) => {
            if (field in model.value && Number.isNaN(model.value[field])) {
                delete model.value[field];
                revalidateNeeded = true;
            }
        });
        return revalidateNeeded;
    };

    const clearEmptyOrDependentFields = () => {
        for (const [fieldName, value] of Object.entries(model.value)) {
            if (value == null || value === '') {
                delete model.value[fieldName];
                continue;
            }
            const field = fields.value[fieldName];
            if (field?.dependency && model.value[field.dependency] == null) {
                delete model.value[fieldName];
            }
        }
    };

    const updateYearBounds = () => {
        if ('year_from' in fields.value && 'year_to' in fields.value) {
            fields.value.year_to.min = model.value.year_from != null ? Math.max(0, model.value.year_from) : 0;
            fields.value.year_from.max = model.value.year_to != null ? Math.min(9999, model.value.year_to) : 9999;
        }
    };

    const initFromURL = (aggregation) => {
        const params = qs.parse(window.location.href.split('?', 2)[1]);

        if ('filters' in params) {
            for (const key of Object.keys(params.filters)) {
                if (key === 'date') {
                    if ('from' in params.filters.date) {
                        model.value.year_from = Number(params.filters.date.from);
                    }
                    if ('to' in params.filters.date) {
                        model.value.year_to = Number(params.filters.date.to);
                    }
                } else if (key in fields.value) {
                    const field = fields.value[key];
                    if (field.type === 'multiselectClear' && aggregation?.[key]) {
                        const values = params.filters[key];
                        const aggValues = aggregation[key];

                        model.value[key] = Array.isArray(values)
                            ? aggValues.filter(v => values.includes(String(v.id)))
                            : [aggValues.find(v => String(v.id) === values)].filter(Boolean);
                    } else if (key.endsWith('_mode')) {
                        model.value[key] = [params.filters[key]];
                    } else {
                        model.value[key] = params.filters[key];
                    }
                }
            }
        }

        oldFilterValues.value = constructFilterValues(model.value, fields.value);

        actualRequest.value = false;

        if ('page' in params) {
            actualRequest.value = false;
            resultTableRef.value?.setPage(params.page);
        }

        if ('orderBy' in params) {
            const asc = 'ascending' in params && params.ascending;
            resultTableRef.value?.setOrder(params.orderBy, asc);
        } else if (
            'filters' in params &&
            (
                (params.filters.text != null && params.filters.text !== '') ||
                (params.filters.comment != null && params.filters.comment !== '')
            )
        ) {
            resultTableRef.value?.setOrder(null);
        } else {
            resultTableRef.value?.setOrder(defaultOrdering.value, true);
        }
    };

    const onValidated = (isValid) => {
        if (!isValid) {
            if (clearInvalidYearFields()) return 'revalidate';
            clearPendingTimeout();
            return;
        }

        clearEmptyOrDependentFields();
        updateYearBounds();
        clearPendingTimeout();

        const isInput = lastChangedField.value && fields.value[lastChangedField.value]?.type === 'input';
        const timeoutValue = isInput ? 1000 : 0;

        if (['text', 'comment'].includes(lastChangedField.value)) {
            actualRequest.value = false;
            const lastValue = model.value[lastChangedField.value];
            if (!lastValue) {
                if (!lastOrder.value) {
                    resultTableRef.value?.setOrder(defaultOrdering.value, true);
                } else {
                    resultTableRef.value?.setOrder(lastOrder.value.column, lastOrder.value.ascending ?? false);
                }
            } else {
                lastOrder.value = structuredClone(resultTableRef.value?.options.orderBy);
                resultTableRef.value?.setOrder(null);
            }
        }

        if (lastChangedField.value === 'text_type') {
            actualRequest.value = !!model.value.text;
            if (actualRequest.value) {
                resultTableRef.value?.setOrder(null);
            }
        } else {
            actualRequest.value = true;
        }

        if (historyRequest.value) {
            actualRequest.value = false;
        }

        inputCancel.value = setTimeout(() => {
            inputCancel.value = null;
            const filterValues = constructFilterValues(model.value, fields.value);
            if (JSON.stringify(filterValues) !== JSON.stringify(oldFilterValues.value)) {
                oldFilterValues.value = filterValues;
                emitFilter(filterValues);
            }
        }, timeoutValue);
    };

    const clearPendingTimeout = () => {
        if (inputCancel.value !== null) {
            clearTimeout(inputCancel.value);
            inputCancel.value = null;
        }
    };

    return {
        onValidated,
        lastChangedField,
        actualRequest,
        initFromURL,
    };
}
