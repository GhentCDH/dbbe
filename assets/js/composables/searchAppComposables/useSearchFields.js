import { ref, computed, watch } from 'vue';
import { sortByName } from "@/helpers/searchAppHelpers/sortUtil";
import {dependencyField, enableField} from "@/helpers/formFieldUtils";
import {changeMode} from "@/helpers/formatUtil";

export function useSearchFields(model, schema, fields, aggregation, {
    multiple = false,
    updateCountRecords,
    initFromURL,
    initFromUrl,
    endRequest,
    historyRequest
} = {}) {
    const textSearch = ref(false);
    const commentSearch = ref(false);
    const lemmaSearch = ref(false);
    const initialized = ref(false);


    const notEmptyFields = computed(() => {
        const show = [];

        const collectFilters = (field) => {
            show.push(...addActiveFilter(field.model || field));
        };

        if (!schema.value) return show;

        if (schema.value.fields) {
            Object.values(schema.value.fields).forEach(collectFilters);
        }

        if (schema.value.groups) {
            schema.value.groups.forEach(group => {
                (group.fields || []).forEach(collectFilters);
            });
        }

        return show;
    });

    function changeTextMode(value, oldValue, modelName) {
        if (
            value == null ||
            model.value[modelName] == null ||
            JSON.stringify(value) === JSON.stringify(oldValue)
        ) return;

        model.value[modelName] = changeMode(oldValue[0], value[0], model.value[modelName]);
    }

    function setUpOperatorWatchers() {
        for (const fieldName of Object.keys(model.value)) {
            if (fieldName.endsWith('_op')) {
                const parentFieldName = fieldName.slice(0, -3);
                watch(() => model.value[parentFieldName], (newValue) => {
                    if (newValue?.length === 1 && model.value[fieldName] === 'and') {
                        model.value[fieldName] = 'or';
                    }
                });
            }
        }
    }

    function onDataExtend(data) {
        textSearch.value = data.data.some(item =>
            'text' in item || 'title' in item || 'title_GR' in item || 'title_LA' in item
        );

        commentSearch.value = data.data.some(item =>
            'public_comment' in item || 'private_comment' in item ||
            'palaeographical_info' in item || 'contextual_info' in item
        );

        lemmaSearch.value = data.data.some(item => 'lemma_text' in item);
    }

    function onLoaded() {
        updateCountRecords();

        if (!initialized.value) {
            initFromURL(aggregation.value);
            initialized.value = true;
        }

        if (historyRequest.value) {
            initFromUrl(aggregation.value);
            historyRequest.value = false;
        }

        for (const fieldName of Object.keys(fields.value)) {
            const field = fields.value[fieldName];

            if (field.type === 'multiselectClear') {
                field.values = aggregation.value[fieldName]?.sort(sortByName) ?? [];
                field.originalValues = JSON.parse(JSON.stringify(field.values));

                if (field.dependency && model.value[field.dependency] == null) {
                    dependencyField(field, model.value);
                } else {
                    enableField(field, null, true);
                }
            }

            if (field.multiDependency != null) {
                field.disabled = !(model.value[field.multiDependency]?.length >= 2);
            }
        }

        updateCountRecords();
        endRequest();
    }

    function addActiveFilter(key) {
        const show = [];
        const field = fields.value[key];
        const currentKey = field.model;
        const value = model.value[currentKey];
        const label = field.label;

        const isIgnored =
            currentKey === 'text_combination' ||
            currentKey === 'text_fields' ||
            currentKey === 'date_search_type' ||
            currentKey === 'title_type' ||
            currentKey === 'management_inverse' ||
            currentKey.endsWith('_mode');

        if (value === undefined ||
            value === null ||
            value === '' ||
            (typeof value === 'number' && isNaN(value)) ||
            isIgnored) {
            return show;
        }
        if (currentKey.endsWith('_op')) {
            if (value !== 'or') {
                show.push({
                    key: currentKey,
                    value: [{ name: '' }],
                    label: field.switchLabel,
                    type: 'switch',
                });
            }
        } else if (Array.isArray(value)) {
            if (value.length) {
                show.push({
                    key: currentKey,
                    value,
                    label,
                    type: 'array',
                });
            }
        } else {
            const mode = model.value[`${key}_mode`]?.[0];
            const normalizedValue = value.name !== undefined ? [value] : [{ name: value }];

            show.push({
                key: currentKey,
                value: normalizedValue,
                label,
                type: typeof value,
                ...(mode ? { mode } : {}),
            });
        }

        return show;
    }

    function deleteActiveFilter({ key, valueIndex }, onValidated) {
        if (key === 'year_from' || key === 'year_to') {
            model.value[key] = undefined;
        } else if (valueIndex === -1) {
            model.value[key] = 'or';
        } else if (valueIndex === -2) {
            model.value[key] = '';
        } else {
            model.value[key].splice(valueIndex, 1);
        }

        onValidated?.(true);
    }

    return {
        textSearch,
        commentSearch,
        lemmaSearch,
        fields,
        notEmptyFields,
        changeTextMode,
        setUpOperatorWatchers,
        onDataExtend,
        onLoaded,
        addActiveFilter,
        deleteActiveFilter,
    };
}
