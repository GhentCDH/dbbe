import { ref } from 'vue';
import qs from 'qs';
import { constructFilterValues } from '@/helpers/searchAppHelpers/filterUtil';

export function useFormValidation({
                                      model,
                                      fields,
                                      defaultOrdering,
                                      historyRequest,
                                      currentPage = ref(1),
                                      sortBy = ref('incipit'),
                                      sortAscending = ref(true),
                                      onDataRefresh = () => {}
                                  }) {
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

    // Helper functions to replace resultTableRef methods
    const setPage = (page) => {
        currentPage.value = page;
        onDataRefresh(true); // Force refresh for pagination
    };

    const setOrder = (column, ascending = true) => {
        if (column === null) {
            // Clear sorting - use default or keep current
            return;
        }
        sortBy.value = column;
        sortAscending.value = ascending;
        currentPage.value = 1; // Reset to first page when sorting changes
        onDataRefresh(true); // Force refresh for sorting
    };

    const getCurrentOrder = () => {
        return {
            column: sortBy.value,
            ascending: sortAscending.value
        };
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
            setPage(params.page);
        }

        if ('orderBy' in params) {
            const asc = 'ascending' in params && params.ascending;
            setOrder(params.orderBy, asc);
        } else if (
            'filters' in params &&
            (
                (params.filters.text != null && params.filters.text !== '') ||
                (params.filters.comment != null && params.filters.comment !== '')
            )
        ) {
            setOrder(null);
        } else {
            setOrder(defaultOrdering.value, true);
        }
    };

    const onValidated = (isValid) => {
        console.log('onValidated called with isValid:', isValid, 'lastChangedField:', lastChangedField.value);

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

        // Handle text/comment fields specially for sorting
        if (['text', 'comment'].includes(lastChangedField.value)) {
            const lastValue = model.value[lastChangedField.value];
            if (!lastValue) {
                if (!lastOrder.value) {
                    setOrder(defaultOrdering.value, true);
                } else {
                    setOrder(lastOrder.value.column, lastOrder.value.ascending ?? false);
                }
            } else {
                lastOrder.value = structuredClone(getCurrentOrder());
                setOrder(null);
            }
        }

        // Set actualRequest to true for most field changes
        if (lastChangedField.value === 'text_type') {
            actualRequest.value = !!model.value.text;
            if (actualRequest.value) {
                setOrder(null);
            }
        } else {
            // Always set to true for filtering - this was the main issue
            actualRequest.value = true;
        }

        // Don't trigger request if this is from history navigation
        if (historyRequest.value) {
            actualRequest.value = false;
            console.log('Skipping request due to historyRequest');
            return;
        }

        console.log('Setting up timeout with actualRequest:', actualRequest.value, 'timeout:', timeoutValue);

        inputCancel.value = setTimeout(() => {
            console.log('Timeout executing, actualRequest:', actualRequest.value);
            inputCancel.value = null;

            const filterValues = constructFilterValues(model.value, fields.value);
            const hasFilterChanges = JSON.stringify(filterValues) !== JSON.stringify(oldFilterValues.value);

            console.log('Filter values changed:', hasFilterChanges);
            console.log('Old filters:', oldFilterValues.value);
            console.log('New filters:', filterValues);

            if (hasFilterChanges) {
                oldFilterValues.value = filterValues;
                currentPage.value = 1; // Reset to first page for new searches

                // Force the data refresh regardless of actualRequest value
                console.log('Calling onDataRefresh with force=true');
                onDataRefresh(true); // Pass true to force the request
            } else {
                console.log('No filter changes detected, skipping refresh');
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
        setPage,
        setOrder,
        getCurrentOrder
    };
}