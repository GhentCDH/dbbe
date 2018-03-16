export default {
    props: {
        header: {
            type: String,
            default: ''
        },
        model: {
            type: Object,
            default: () => {return {}}
        },
        values: {
            type: Array,
            default: () => {return []}
        },
    },
    data () {
        return {
            changes: [],
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            isValid: true,
            originalModel: {},
        }
    },
    computed: {
        fields() {
            return this.schema.fields
        }
    },
    watch: {
        model() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        }
    },
    methods: {
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    this.changes.push({
                        'key': key,
                        'label': this.fields[key].label,
                        'old': this.originalModel[key],
                        'new': this.model[key],
                        'value': this.model[key],
                    })
                }
            }
        },
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
        disableField(field) {
            field.disabled = true
            field.placeholder = 'Loading'
            field.selectOptions.loading = true
            field.values = []
        },
        dependencyField(field) {
            this.model[field.model] = null
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'Please select a ' + field.dependency + ' first'
        },
        enableField(field) {
            if (field.values.length === 0) {
                return this.noValuesField(field)
            }


            // only keep current value(s) if it is in the list of possible values
            if (this.model[field.model] != null) {
                if (Array.isArray(this.model[field.model])) {
                    let newValues = []
                    for (let index of this.model[field.model].keys()) {
                        if ((field.values.filter(v => v.id === this.model[field.model][index].id)).length !== 0) {
                            newValues.push(this.model[field.model][index])
                        }
                    }
                    this.model[field.model] = newValues
                }
                else if ((field.values.filter(v => v.id === this.model[field.model].id)).length === 0) {
                    this.model[field.model] = null
                }
            }

            field.selectOptions.loading = false
            field.disabled = false
            let label = field.label.toLowerCase()
            let article = ['origin'].indexOf(label) < 0 ? 'a ' : 'an '
            field.placeholder = (field.selectOptions.multiple ? 'Select ' : 'Select ' + article) + label
        },
        noValuesField(field) {
            this.model[field.model] = null
            field.disabled = true
            field.selectOptions.loading = false
            field.placeholder = 'No ' + field.label.toLowerCase() + 's available'
        }
    }
}
