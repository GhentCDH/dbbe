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

import {createMultiSelect, disableFields, enableFields,} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
import validatorUtil from "@/helpers/validatorUtil";
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.component('panel', Panel)

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
                    statuses: {field: 'status', init: true},
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
        let data =  {
            schema: {
                fields: {
                    publicComment: {
                        type: 'textArea',
                        label: 'Public comment',
                        labelClasses: 'control-label',
                        model: 'publicComment',
                        rows: 4, props: {
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
                        keys: {
                          type: Object,
                          default: () => {return {}},
                        },
                        reloads: {
                          type: Array,
                          default: () => {return []},
                        },
                        values: {
                          type: Array,
                          default: () => {return []},
                        },
                      },

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
                    illustrated: {
                        type: 'checkbox',
                        styleClasses: 'has-warning',
                        label: 'Illustrated',
                        labelClasses: 'control-label',
                        model: 'illustrated',
                    },
                    public: {
                        type: 'checkbox',
                        styleClasses: 'has-error',
                        label: 'Public',
                        labelClasses: 'control-label',
                        model: 'public',
                    },
                }
            },

        }
        if(this.values){
          data.schema.fields.acknowledgements = createMultiSelect(
              'Acknowledgements',
              {
                model: 'acknowledgements',
                values: this.values.acknowledgements,
              },
              {
                multiple: true,
                closeOnSelect: false,
              }
          )
          data.schema.fields.statuses = createMultiSelect(createMultiSelect('Status', {values: this.values.statuses}, {}),)
        }
        return {
          changes: [],
          formOptions: {
            validateAfterChanged: true,
            validationErrorClass: 'has-error',
            validationSuccessClass: 'success',
          },
          isValid: true,
          originalModel: {},
          ...data
        }

    },
  watch: {
    'values.acknowledgements'(newVal) {
      if (newVal && newVal.length > 0 && this.schema.fields.acknowledgements) {
        this.schema.fields.acknowledgements.values = newVal;
      }
    },
    'values.statuses'(newVal) {
      if (newVal && newVal.length > 0 && this.schema.fields.statuses) {
        this.schema.fields.statuses.values = newVal;
      }
    }
  },
    methods: {
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
