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
} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
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
                    recordStatuses: {field: 'recordStatus', init: true},
                    dividedStatuses: {field: 'dividedStatus', init: true},
                    sourceStatuses: {field: 'sourceStatus', init: true},
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

    data() {
        return {
            schema: {
                fields: {
                    palaeographicalInfo: {
                        type: 'textArea',
                        label: 'Palaeographical information',
                        labelClasses: 'control-label',
                        model: 'palaeographicalInfo',
                        rows: 4,
                        validator: validatorUtil.string,
                    },
                    contextualInfo: {
                        type: 'textArea',
                        label: 'Contextual information',
                        labelClasses: 'control-label',
                        model: 'contextualInfo',
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
                    textStatus: createMultiSelect('Text Status', {model: 'textStatus'}),
                    recordStatus: createMultiSelect('Record Status', {model: 'recordStatus'}),
                    dividedStatus: createMultiSelect('Verses correctly divided', {model: 'dividedStatus'}),
                    sourceStatus: createMultiSelect('Source', {model: 'sourceStatus'}),
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
    methods: {
      init() {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        this.enableFields()
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
