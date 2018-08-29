export default {
    methods: {
        createMultiSelect(label, extra = null, extraSelectOptions = null) {
            let result = {
                type: 'multiselectClear',
                label: label,
                labelClasses: 'control-label',
                placeholder: 'Loading',
                // lowercase first letter + remove spaces
                model: label.charAt(0).toLowerCase() + label.slice(1).replace(/[ ]/g, ''),
                // Values will be loaded using a watcher or Ajax request
                values: [],
                selectOptions: {
                    customLabel: ({id, name}) => {
                        return name
                    },
                    showLabels: false,
                    loading: true,
                    trackBy: 'id',
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

            // get everything after last '.'
            let modelName = field.model.split('.').pop()

            delete model[modelName]
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'Please select a ' + (field.dependencyName ? field.dependencyName : field.dependency) + ' first'
        },
        enableField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            if (field.values.length === 0) {
                return this.noValuesField(field, model)
            }

            // get everything after last '.'
            let modelName = field.model.split('.').pop()

            // only keep current value(s) if it is in the list of possible values
            if (model[modelName] != null) {
                if (Array.isArray(model[modelName])) {
                    let newValues = []
                    for (let index of model[modelName].keys()) {
                        if ((field.values.filter(v => v.id === model[modelName][index].id)).length !== 0) {
                            newValues.push(model[modelName][index])
                        }
                    }
                    model[modelName] = newValues
                }
                else if ((field.values.filter(v => v.id === model[modelName].id)).length === 0) {
                    model[modelName] = null
                }
            }

            field.selectOptions.loading = false
            field.disabled = false
            let label = field.label.toLowerCase()
            let article = ['office', 'origin'].indexOf(label) < 0 ? 'a ' : 'an '
            field.placeholder = (field.selectOptions.multiple ? 'Select ' : 'Select ' + article) + label
        },
        loadLocationField(field, model = null) {
            if (model == null) {
                model = this.model
            }
            let locations = this.values

            // filter dependency
            if (field.hasOwnProperty('dependency')) {
                switch (field.dependency) {
                case 'regionWithParents':
                    locations = locations.filter((location) => location.regionWithParents.id === model.regionWithParents.id)
                    break
                case 'institution':
                    locations = locations.filter((location) => ( location.institution != null && location.institution.id === model.institution.id))
                    break
                }
            }

            // get everything after last '.'
            let modelName = field.model.split('.').pop()

            // filter null values
            switch (modelName) {
            case 'institution':
                locations = locations.filter((location) => location.institution != null)
                break
            case 'collection':
                locations = locations.filter((location) => location.collection != null)
                break
            }

            let values = locations
                // get the requested field information
                .map((location) => {
                    let fieldInfo = {
                        locationId: location.id
                    }
                    switch (modelName) {
                    case 'regionWithParents':
                        fieldInfo.id = location.regionWithParents.id
                        fieldInfo.name = location.regionWithParents.name
                        fieldInfo.individualName = location.regionWithParents.individualName
                        fieldInfo.historicalName = location.regionWithParents.historicalName
                        fieldInfo.individualHistoricalName = location.regionWithParents.individualHistoricalName
                        break
                    case 'institution':
                        fieldInfo.id = location.institution.id
                        fieldInfo.name = location.institution.name
                        break
                    case 'collection':
                        fieldInfo.id = location.collection.id
                        fieldInfo.name = location.collection.name
                        break
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

            // get everything after last '.'
            let modelName = field.model.split('.').pop()

            model[modelName] = null
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'No ' + field.label.toLowerCase() + 's available'
        },
    }
}
