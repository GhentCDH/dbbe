export function createMultiSelect(label, extra = {}, extraSelectOptions = {}) {
    return {
        type: 'multiselectClear',
        label,
        labelClasses: 'control-label',
        placeholder: 'Loading',
        model: label.charAt(0).toLowerCase() + label.slice(1).replace(/\s/g, ''),
        values: [],
        selectOptions: {
            customLabel: ({ _id, name }) => name,
            showLabels: false,
            loading: true,
            trackBy: 'id',
            ...extraSelectOptions,
        },
        disabled: true,
        ...extra,
    };
}

export function createMultiMultiSelect(label, extra = {}, extraSelectOptions = {}) {
    const systemName = extra?.model ?? label.toLowerCase().replace(/\s/g, '_');
    return [
        {
            disabled: true,
            switchLabel: `${label} and`,
            type: 'switch',
            model: `${systemName}_op`,
            textOn: 'Or',
            textOff: 'And',
            valueOn: 'or',
            valueOff: 'and',
            multiDependency: systemName,
        },
        createMultiSelect(label, extra, {
            multiple: true,
            closeOnSelect: false,
            ...extraSelectOptions,
        }),
    ];
}

export function createLanguageToggle(label, extra = {}) {
    return {
        type: 'checkboxes',
        styleClasses: 'field-inline-options field-checkboxes-labels-only field-checkboxes-sm',
        model: `${label}_mode`,
        parentModel: label,
        values: [
            { name: 'GREEK', value: 'greek', toggleGroup: 'greek_betacode_latin' },
            { name: 'BETACODE', value: 'betacode', toggleGroup: 'greek_betacode_latin' },
            { name: 'LATIN', value: 'latin', toggleGroup: 'greek_betacode_latin' },
        ],
        ...extra,
    };
}

export function disableField(field) {
    field.disabled = true;
    field.placeholder = 'Loading';
    field.selectOptions.loading = true;
    field.values = [];
}

export function disableFields(keys, fields, disableKeys) {
    for (const key of Object.keys(keys)) {
        if (disableKeys.includes(key)) {
            disableField(fields[keys[key].field]);
        }
    }
}
export function dependencyField(field, model) {
    const modelName = field.model.split('.').pop();
    delete model[modelName];
    field.disabled = true;
    field.selectOptions.loading = false;
    field.placeholder = `Please select a ${field.dependencyName ?? field.dependency} first`;
}

export function enableField(field, model, search = false) {
    const modelName = field.model?.split('.').pop() || '';
    if (!field || field.length === 0) {
        noValuesField(field, model, search);
        return;
    }
    if (model  != null && model[modelName] != null) {
        if (Array.isArray(model[modelName])) {
            model[modelName] = model[modelName].filter((item) =>
                field.some((v) => v.id === item.id)
            );
        } else if (!field.some((v) => v.id === model[modelName].id)) {
            model[modelName] = null;
        }
    }

    field.selectOptions.loading = false;
    field.disabled = field.originalDisabled ?? false;

    const label = field.label.toLowerCase();
    const useAn = ['article', 'office', 'online source', 'origin', 'editorial status', 'id'];
    const article = useAn.includes(label) ? 'an ' : label === 'acknowledgements' ? '' : 'a ';
    field.placeholder = (field.selectOptions.multiple ? 'Select ' : `Select ${article}`) + label;

    if (field.model === 'diktyon') {
        field.placeholder = 'Select a Diktyon number';
    }
}

export function enableFields(keys, fields, values, enableKeys = null,model=null) {
    for (const key of Object.keys(keys)) {
        const { field, init } = keys[key];

        if ((init && enableKeys == null) || (enableKeys && enableKeys.includes(key))) {
            if (!fields[field]) continue;

            const fieldValues = Array.isArray(values) ? values : values?.[key];

            fields[field].values = fieldValues;
            fields[field].originalValues = JSON.parse(JSON.stringify(fieldValues));

            enableField(fields[field], model);
        }
    }
}
export function loadLocationField(field, model, values) {
    const modelName = field.model.split('.').pop();

    let locations = values;

    if (field.dependency) {
        const depId = model[field.dependency]?.id;
        switch (field.dependency) {
            case 'regionWithParents':
                locations = locations.filter((l) => l.regionWithParents?.id === depId);
                break;
            case 'institution':
                locations = locations.filter((l) => l.institution?.id === depId);
                break;
        }
    }

    // Filter nulls based on model
    if (modelName === 'institution') {
        locations = locations.filter((l) => l.institution);
    } else if (modelName === 'collection') {
        locations = locations.filter((l) => l.collection);
    }

    const fieldValues = locations.map((location) => {
        const fieldInfo = { locationId: location.id };
        switch (modelName) {
            case 'regionWithParents':
                Object.assign(fieldInfo, location.regionWithParents);
                break;
            case 'institution':
                fieldInfo.id = location.institution.id;
                fieldInfo.name = location.institution.name;
                break;
            case 'collection':
                fieldInfo.id = location.collection.id;
                fieldInfo.name = location.collection.name;
                break;
        }
        return fieldInfo;
    });

    field.values = fieldValues.filter(
        (location, index, self) => index === self.findIndex((l) => l.id === location.id)
    );
}

export function noValuesField(field, model, search = false) {
    const modelName = field.model.split('.').pop();
    if (!search) delete model[modelName];

    field.disabled = true;
    field.selectOptions.loading = false;
    field.placeholder = `No ${field.label.toLowerCase()}s available`;
}

export function removeGreekAccents(input) {
    const encoded = encodeURIComponent(input.normalize('NFD'));
    const stripped = encoded.replace(/%C[^EF]%[0-9A-F]{2}/gi, '');
    return decodeURIComponent(stripped).toLocaleLowerCase();
}
