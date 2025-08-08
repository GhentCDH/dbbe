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
import Vue from 'vue';
import {
  createMultiSelect, disableFields, enableFields,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
import validatorUtil from "@/helpers/validatorUtil";
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.component('panel', Panel);

validatorUtil.name = function(value, field, model) {
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
        header: {
          type: String,
          default: '',
        },
        links: {
          type: Array,
          default: () => {return []},
        },
        model: {
          type: Object,
          default: () => {return {}},
        },
        reloads: {
          type: Array,
          default: () => {return []},
        },

    },
  computed: {
    fields() {
      return this.schema.fields
    }
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
                        validator: [validatorUtil.string, validatorUtil.name],
                    },
                    lastName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Last Name',
                        labelClasses: 'control-label',
                        model: 'lastName',
                        validator: [validatorUtil.string, validatorUtil.name],
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
                        validator: [validatorUtil.string, validatorUtil.name],
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
            changes: [],
            formOptions: {
              validateAfterChanged: true,
              validationErrorClass: 'has-error',
              validationSuccessClass: 'success',
            },
            isValid: true,
            originalModel: {}
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
        init() {
          this.originalModel = JSON.parse(JSON.stringify(this.model));
          this.enableFields();
        },
        reload(type) {
          if (!this.reloads.includes(type)) {
            this.$emit('reload', type);
          }
        },
        disableFields(disableKeys) {
          disableFields(this.keys, this.fields, disableKeys);
        },
        enableFields(enableKeys) {
          enableFields(this.keys, this.fields, this.values, enableKeys);
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
        },
        validate() {
          this.$refs.form.validate()
        },
    },
}
</script>
