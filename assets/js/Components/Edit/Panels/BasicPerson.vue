<template>
    <panel
        :header="header"
        :links="links"
        :reloads="reloads"
        @reload="reload"
    >
        <vue-form-generator
            ref="form"
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated"
        />
    </panel>
</template>
<script>
import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../../../mixins/AbstractPanelForm'
import {
  createMultiSelect,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import Panel from '../Panel'

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

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
};

export default {
    mixins: [
        AbstractPanelForm,
    ],
    props: {
        values: {
            type: Object,
            default: () => {
                return {}
            }
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    selfDesignations: {field: 'selfDesignations', init: true},
                    offices: {field: 'offices', init: true},
                    origins: {field: 'origin', init: true},
                };
            },
        },
    },
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
                    dbbe: {
                        type: 'checkbox',
                        label: 'DBBE',
                        labelClasses: 'control-label',
                        model: 'dbbe',
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
                    selfDesignations: createMultiSelect(
                        '(Self) designation',
                        {
                            model: 'selfDesignations',
                            styleClasses: 'greek',
                            originalDisabled: (model) => {
                                return model && !model.historical;
                            },
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                            internalSearch: false,
                            onSearch: this.greekSearch,
                        }

                    ),
                    offices: createMultiSelect(
                        'Offices',
                        {
                            originalDisabled: (model) => {
                                return model && !model.historical;
                            },
                        },
                        {
                            multiple: true, closeOnSelect: false
                        }
                    ),
                    origin: createMultiSelect(
                        'Provenance',
                        {
                            model: 'origin',
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
        calcChanges() {
            this.changes = [];
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                // Remove selfdesignations, offices, origin or extra if not historical
                if (!this.model.historical) {
                    if (
                        ['selfDesignations', 'offices'].includes(key)
                        && this.originalModel[key] != null
                        && this.originalModel[key].length !== 0
                    ) {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.originalModel[key],
                            'new': [],
                            'value': [],
                        })
                        continue;
                    }
                    if (
                        key == 'origin'
                        && this.originalModel[key] != null
                        && Object.keys(this.originalModel[key]).length === 0
                    ) {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.originalModel[key],
                            'new': null,
                            'value': null,
                        })
                        continue;
                    }
                    if (
                        key == 'extra'
                        && this.originalModel[key] != null
                        && this.originalModel[key] !== ''
                    ) {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.originalModel[key],
                            'new': null,
                            'value': '',
                        })
                        continue;
                    }
                }
                if (
                    JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])
                    && !(this.model[key] == null && this.originalModel[key] == null)
                ) {
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
        greekSearch(searchQuery) {
            this.schema.fields.selfDesignations.values = this.schema.fields.selfDesignations.originalValues.filter(
                option => removeGreekAccents(`${option.id} - ${option.name}`).includes(removeGreekAccents(searchQuery))
            );
        },
    },
}
</script>
