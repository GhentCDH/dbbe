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

Vue.component('panel', Panel)

export default {
    props: {
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
        values: {
          type: Array,
          default: () => {return []},
        },
        keys: {
            type: Object,
            default: () => {
                return {keywords: {field: 'keywords', init: true}};
            },
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
                      keywords: createMultiSelect(
                          'Tags',
                          {
                              model: 'keywords'
                          },
                          {
                              multiple: true,
                              closeOnSelect: false,
                          }
                      ),
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
