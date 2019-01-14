<template>
    <panel
        :header="header"
        :link="link">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="form"
            @validated="validated" />
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

VueFormGenerator.validators.name = function(value, field, model) {
    if (
        (model.firstName == null || model.firstName === '')
        && (model.lastName == null || model.lastName === '')
        && (model.extra == null || model.extra === '')
        && (model.unprocessed == null || model.unprocessed === '')
    ) {
        return ['At least one of the fields "First Name", "Last Name", "Extra" is required.']
    }

    return []
}

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    data: function () {
        return {
            schema: {
                fields: {
                    historical: {
                        type: 'checkbox',
                        label: 'Historical',
                        labelClasses: 'control-label',
                        model: 'historical',
                    },
                    modern: {
                        type: 'checkbox',
                        label: 'Modern',
                        labelClasses: 'control-label',
                        model: 'modern',
                    },
                    firstName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'First Name',
                        labelClasses: 'control-label',
                        model: 'firstName',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                    },
                    lastName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Last Name',
                        labelClasses: 'control-label',
                        model: 'lastName',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                    },
                    selfDesignations: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Self designation',
                        labelClasses: 'control-label',
                        model: 'selfDesignations',
                        validator: [VueFormGenerator.validators.regexp],
                        pattern: '^(?:(?:[\\u0370-\\u03ff\\u1f00-\\u1fff ]+)[,][ ])*(?:[\\u0370-\\u03ff\\u1f00-\\u1fff ]+)$',
                        hint: 'Greek text only; comma (,) separated list. E.g., ελληνικά,καλημέρα',
                        disabled: (model) => {
                            return model && !model.historical;
                        },
                    },
                    origin: this.createMultiSelect(
                        'Origin',
                        {
                            values: this.values,
                            originalDisabled: (model) => {
                                return model && !model.historical;
                            },
                        }

                    ),
                    extra: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Extra',
                        labelClasses: 'control-label',
                        model: 'extra',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                        disabled: (model) => {
                            return model && !model.historical;
                        },
                    },
                    unprocessed: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Unprocessed',
                        labelClasses: 'control-label',
                        model: 'unprocessed',
                        disabled: true,
                    },
                }
            },
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.fields.origin)
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                // Remove selfdesignations, origin or extra if not historical
                if (!this.model.historical && ['selfDesignations', 'origin', 'extra'].includes(key)) {
                    if (
                        this.originalModel[key] != null
                        && (
                            ((['selfDesignations', 'extra'].includes(key)) && this.originalModel[key] != '')
                            || this.originalModel[key] != []
                        )
                    ) {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.originalModel[key],
                            'new': null,
                            'value': ['selfDesignations', 'extra'].includes(key) ? '' : null,
                        })
                    }
                    continue;
                }
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
    },
}
</script>
