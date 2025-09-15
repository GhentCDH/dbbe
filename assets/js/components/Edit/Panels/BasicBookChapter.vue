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
import validatorUtil from "@/helpers/validatorUtil";
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.component('panel', Panel)

export default {
    props: {
        keys: {
            type: Object,
            default: () => {
                return {
                    books: {field: 'book', init: false},
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
        values: {
          type: Array,
          default: () => {return []},
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
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: validatorUtil.string,
                    },
                    book: {
                      ...createMultiSelect(
                      'Book'),
                      required: true,
                      validator: [validatorUtil.required],
                      },
                    startPage: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Start Page',
                        labelClasses: 'control-label',
                        model: 'startPage',
                        validator: [validatorUtil.number, this.startBeforeEndValidator, this.endWithoutStartValidator],
                    },
                    endPage: {
                        type: 'input',
                        inputType: 'number',
                        label: 'End Page',
                        labelClasses: 'control-label',
                        model: 'endPage',
                        validator: [validatorUtil.number, this.startBeforeEndValidator, this.endWithoutStartValidator],
                    },
                    rawPages: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Raw Pages',
                        labelClasses: 'control-label',
                        model: 'rawPages',
                        validator: validatorUtil.number,
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
        startBeforeEndValidator() {
            if (this.model.startPage != null && this.model.endPage != null) {
                if (this.model.startPage > this.model.endPage) {
                    return ['End page must be larger than start page.'];
                }
            }
            return [];
        },
        endWithoutStartValidator() {
            if (this.model.startPage == null && this.model.endPage != null) {
                return ['If an end page is defined, a start page must be defined as well.'];
            }
            return [];
        },
        validate() {
          this.$refs.form.validate()
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
        },
        disableFields(disableKeys) {
          disableFields(this.keys, this.fields, disableKeys);
        },
        enableFields(enableKeys) {
          enableFields(this.keys, this.fields, this.values, enableKeys);
        },
        reload(type) {
          if (!this.reloads.includes(type)) {
            this.$emit('reload', type);
          }
        },

  },
}
</script>
