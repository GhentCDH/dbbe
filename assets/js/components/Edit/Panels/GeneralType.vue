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

import {
  createMultiSelect, disableFields, enableFields,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import {calcChanges} from "@/helpers/modelChangeUtil";
import validatorUtil from "@/helpers/validatorUtil";


export default {
    props: {
        values: {
            type: Object,
            default: () => {return {}}
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    acknowledgements: {field: 'acknowledgements', init: true},
                    textStatuses: {field: 'textStatus', init: true},
                    criticalStatuses: {field: 'criticalStatus', init: true},
                    occurrences: {field: 'basedOn', init: false},
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
    data() {
        return {
            schema: {
                fields: {
                    criticalApparatus: {
                        type: 'textArea',
                        label: 'Critical apparatus',
                        labelClasses: 'control-label',
                        model: 'criticalApparatus',
                        rows: 4,
                        validator: validatorUtil.string,
                    },
                    acknowledgements: createMultiSelect(
                        'Acknowledgements',
                        {
                            model: 'acknowledgements',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                    publicComment: {
                        type: 'textArea',
                        label: 'Public comment',
                        labelClasses: 'control-label',
                        model: 'publicComment',
                        rows: 4,
                        validator: validatorUtil.string,
                    },
                    privateComment: {
                        type: 'textArea',
                        styleClasses: 'has-warning',
                        label: 'Private comment',
                        labelClasses: 'control-label',
                        model: 'privateComment',
                        rows: 4,
                        validator: validatorUtil.string,
                    },
                    textStatus: createMultiSelect(
                        'Text Status',
                        {
                            model: 'textStatus',
                            required: true,
                            validator: validatorUtil.required,
                        }
                    ),
                    criticalStatus: createMultiSelect(
                        'Editorial Status',
                        {
                            model: 'criticalStatus',
                            required: true,
                            validator: validatorUtil.required,
                        }
                    ),
                    basedOn: createMultiSelect(
                        'Based On (occurrence)',
                        {
                            model: 'basedOn',
                            styleClasses: 'greek',
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                            internalSearch: false,
                            onSearch: this.greekSearch,
                        }
                    ),
                    public: {
                        type: 'checkbox',
                        styleClasses: 'has-error',
                        label: 'Public',
                        labelClasses: 'control-label',
                        model: 'public',
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
    computed: {
    fields() {
      return this.schema.fields
    }
  },

    methods: {
        init() {
          this.originalModel = JSON.parse(JSON.stringify(this.model));
          this.enableFields();
        },
        greekSearch(searchQuery) {
            this.schema.fields.basedOn.values = this.schema.fields.basedOn.originalValues.filter(
                option => removeGreekAccents(`${option.id} - ${option.name}`).includes(removeGreekAccents(searchQuery))
            );
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
