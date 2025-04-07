import {noValuesField} from "./formFieldUtils";

export default {
    methods: {
        createMultiSelect(label, extra = null, extraSelectOptions = null) {
            const result = {
                type: 'multiselectClear',
                label,
                labelClasses: 'control-label',
                placeholder: 'Loading',
                // lowercase first letter + remove spaces
                model: label.charAt(0).toLowerCase() + label.slice(1).replace(/[ ]/g, ''),
                // Values will be loaded using a watcher or Ajax request
                values: [],
                selectOptions: {
                    customLabel: ({ _id, name }) => name,
                    showLabels: false,
                    loading: true,
                    trackBy: 'id',
                },
                // Will be enabled by enableField
                disabled: true,
            };
            if (extra != null) {
                for (const key of Object.keys(extra)) {
                    result[key] = extra[key];
                }
            }
            if (extraSelectOptions != null) {
                for (const key of Object.keys(extraSelectOptions)) {
                    result.selectOptions[key] = extraSelectOptions[key];
                }
            }
            return result;
        },
        createMultiMultiSelect(label, extra = null, extraSelectOptions = null) {
            const systemName = extra?.model ?? label.toLowerCase().replace(' ', '_');
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
                this.createMultiSelect(
                    label,
                    {
                        ...extra,
                    },
                    {
                        ...extraSelectOptions,
                        multiple: true,
                        closeOnSelect: false,
                    },
                ),
            ];
        },
        createLanguageToggle(label, extra = null) {
            const result = {
                type: 'checkboxes',
                styleClasses: 'field-inline-options field-checkboxes-labels-only field-checkboxes-sm',
                model: `${label}_mode`,
                parentModel: label,
                values: [
                    {
                        name: 'GREEK', value: 'greek', toggleGroup: 'greek_betacode_latin',
                    },
                    {
                        name: 'BETACODE', value: 'betacode', toggleGroup: 'greek_betacode_latin',
                    },
                    {
                        name: 'LATIN', value: 'latin', toggleGroup: 'greek_betacode_latin',
                    },
                ],
            };
            if (extra != null) {
                for (const key of Object.keys(extra)) {
                    result[key] = extra[key];
                }
            }
            return result;
        },
        disableField(field, model = null) {
            /* eslint-disable no-param-reassign */
            if (model == null) {
                model = this.model;
            }
            field.disabled = true;
            field.placeholder = 'Loading';
            field.selectOptions.loading = true;
            field.values = [];
            /* eslint-enable no-param-reassign */
        },
        dependencyField(field, model = null) {
            /* eslint-disable no-param-reassign */
            if (model == null) {
                model = this.model;
            }

            // get everything after last '.'
            const modelName = field.model.split('.').pop();

            delete model[modelName];
            field.disabled = true;
            field.selectOptions.loading = false;
            // eslint-disable-next-line max-len
            field.placeholder = `Please select a ${field.dependencyName ? field.dependencyName : field.dependency} first`;
            /* eslint-enable no-param-reassign */
        },
        enableField(field, model = null, search = false) {
            /* eslint-disable no-param-reassign */
            if (model == null) {
                model = this.model;
            }
            if (field.values.length === 0) {
                noValuesField(field, model, search);
                return;
            }

            // get everything after last '.'
            const modelName = field.model.split('.').pop();

            // only keep current value(s) if it is in the list of possible values
            if (model[modelName] != null) {
                if (Array.isArray(model[modelName])) {
                    const newValues = [];
                    for (const index of model[modelName].keys()) {
                        if ((field.values.filter((v) => v.id === model[modelName][index].id)).length !== 0) {
                            newValues.push(model[modelName][index]);
                        }
                    }
                    model[modelName] = newValues;
                } else if ((field.values.filter((v) => v.id === model[modelName].id)).length === 0) {
                    model[modelName] = null;
                }
            }

            field.selectOptions.loading = false;
            field.disabled = field.originalDisabled == null ? false : field.originalDisabled;
            const label = field.label.toLowerCase();
            let article = 'a ';
            switch (label) {
            case 'article':
            case 'office':
            case 'online source':
            case 'origin':
            case 'editorial status':
            case 'id':
                article = 'an ';
                break;
            case 'acknowledgements':
                article = '';
                break;
            default:
                break;
            }
            field.placeholder = (field.selectOptions.multiple ? 'Select ' : `Select ${article}`) + label;
            if (field.model === 'diktyon') {
                field.placeholder = 'Select a Diktyon number';
            }
            /* eslint-enable no-param-reassign */
        },
        loadLocationField(field, model = null) {
            /* eslint-disable no-param-reassign */
            if (model == null) {
                model = this.model;
            }
            let locations = this.values;

            // filter dependency
            if ('dependency' in field) {
                switch (field.dependency) {
                case 'regionWithParents':
                    locations = locations.filter(
                        (location) => location.regionWithParents.id === model.regionWithParents.id,
                    );
                    break;
                case 'institution':
                    locations = locations.filter(
                        // eslint-disable-next-line max-len
                        (location) => (location.institution != null && location.institution.id === model.institution.id),
                    );
                    break;
                default:
                    break;
                }
            }

            // get everything after last '.'
            const modelName = field.model.split('.').pop();

            // filter null values
            switch (modelName) {
            case 'institution':
                locations = locations.filter((location) => location.institution != null);
                break;
            case 'collection':
                locations = locations.filter((location) => location.collection != null);
                break;
            default:
                break;
            }

            const values = locations
                // get the requested field information
                .map((location) => {
                    const fieldInfo = {
                        locationId: location.id,
                    };
                    switch (modelName) {
                    case 'regionWithParents':
                        fieldInfo.id = location.regionWithParents.id;
                        fieldInfo.name = location.regionWithParents.name;
                        fieldInfo.individualName = location.regionWithParents.individualName;
                        fieldInfo.historicalName = location.regionWithParents.historicalName;
                        fieldInfo.individualHistoricalName = location.regionWithParents.individualHistoricalName;
                        break;
                    case 'institution':
                        fieldInfo.id = location.institution.id;
                        fieldInfo.name = location.institution.name;
                        break;
                    case 'collection':
                        fieldInfo.id = location.collection.id;
                        fieldInfo.name = location.collection.name;
                        break;
                    default:
                        break;
                    }
                    return fieldInfo;
                })
                // remove duplicates
                .filter((location, index, self) => index === self.findIndex((l) => l.id === location.id));

            field.values = values;
            /* eslint-enable no-param-reassign */
        },
        noValuesField(field, model = null, search = false) {
            /* eslint-disable no-param-reassign */
            if (model == null) {
                model = this.model;
            }

            // Delete value if not on the search page
            if (!search) {
                // get everything after last '.'
                const modelName = field.model.split('.').pop();
                delete model[modelName];
            }

            field.disabled = true;
            field.selectOptions.loading = false;
            field.placeholder = `No ${field.label.toLowerCase()}s available`;
            /* eslint-enable no-param-reassign */
        },
        removeGreekAccents(input) {
            const encoded = encodeURIComponent(input.normalize('NFD'));
            const stripped = encoded.replace(/%C[^EF]%[0-9A-F]{2}/gi, '');
            return decodeURIComponent(stripped).toLocaleLowerCase();
        },
    },
};
