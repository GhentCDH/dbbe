export default {
    methods: {
        createMultiSelect(label, extra, extraSelectOptions) {
            let result = {
                type: 'multiselectClear',
                label: label,
                labelClasses: 'control-label',
                placeholder: 'Loading',
                // lowercase first letter + remove spaces
                model: label.charAt(0).toLowerCase() + label.slice(1).replace(/[ ]/g, ''),
                // Values will be loaded using a watcher
                values: [],
                selectOptions: {
                    optionsLimit: 10000,
                    customLabel: ({id, name}) => {
                        return name
                    },
                    showLabels: false,
                    loading: true
                },
                // Will be enabled when list of scribes is loaded
                disabled: true
            }
            if (extra != null) {
                for (let key of Object.keys(extra)) {
                    result[key] = extra[key]
                }
            }
            if (extraSelectOptions != null) {
                for (let key of Object.keys(extraSelectOptions)) {
                    result['selectOptions'][key] = extraSelectOptions[key]
                }
            }
            return result
        },
        disableField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            field.disabled = true
            field.placeholder = 'Loading'
            field.selectOptions.loading = true
            field.values = []
        },
        dependencyField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            model[field.model] = null
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'Please select a ' + field.dependency + ' first'
        },
        enableField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            if (field.values.length === 0) {
                return this.noValuesField(field)
            }

            // only keep current value(s) if it is in the list of possible values
            if (model[field.model] != null) {
                if (Array.isArray(model[field.model])) {
                    let newValues = []
                    for (let index of model[field.model].keys()) {
                        if ((field.values.filter(v => v.id === model[field.model][index].id)).length !== 0) {
                            newValues.push(model[field.model][index])
                        }
                    }
                    model[field.model] = newValues
                }
                else if ((field.values.filter(v => v.id === model[field.model].id)).length === 0) {
                    model[field.model] = null
                }
            }

            field.selectOptions.loading = false
            field.disabled = false
            let label = field.label.toLowerCase()
            let article = ['origin'].indexOf(label) < 0 ? 'a ' : 'an '
            field.placeholder = (field.selectOptions.multiple ? 'Select ' : 'Select ' + article) + label
        },
        loadLocationField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            let locations = Object.values(this.values)
            // filter dependency
            if (field.hasOwnProperty('dependency') && model[field.dependency] != null) {
                locations = locations.filter((location) => location[field.dependency + '_id'] === model[field.dependency]['id'])
            }
            // filter null values
            locations = locations.filter((location) => location[field.model + '_id'] != null)

            let values = locations
                // get the requested field information
                .map((location) => {
                    let fieldInfo = {
                        id: location[field.model + '_id'],
                        name: location[field.model + '_name']
                    }
                    if (location[field.model + '_individualName'] != null) {
                        fieldInfo.individualName = location[field.model + '_individualName']
                    }
                    return fieldInfo
                })
                // remove duplicates
                .filter((location, index, self) => index === self.findIndex((l) => l.id === location.id))

            field.values = values
        },
        noValuesField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            model[field.model] = null
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'No ' + field.label.toLowerCase() + 's available'
        },
    }
}
