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
    keys: {
      type: Object,
      default: () => {
        return {managements: {field: 'managements', init: true}};
      },
    },
    values: {
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
      changes: [],
      formOptions: {
        validateAfterChanged: true,
        validationErrorClass: 'has-error',
        validationSuccessClass: 'success',
      },
      isValid: true,
      originalModel: {},
      schema: {
        fields: {
          managements: createMultiSelect(
              'Management collection',
              {
                model: 'managements',
                values: [],
              },
              {
                multiple: true,
                closeOnSelect: false,
              }
          ),
        }
      }
    }
  },
  watch: {
    values: {
      handler(newValues) {
        if (newValues && newValues.length > 0) {
          // Update the existing field instead of recreating the schema
          this.$set(this.schema.fields.managements, 'values', newValues);
          this.enableFields();
        }
      },
      immediate: true,
      deep: true
    }
  },
  created() {
    this.init();
  },
  methods: {
    init() {
      this.originalModel = JSON.parse(JSON.stringify(this.model));
    },
    reload(type) {
      if (!this.reloads.includes(type)) {
        this.$emit('reload', type);
      }
    },
    disableFields(disableKeys) {
      disableFields(this.keys, this.fields, disableKeys);
      this.$forceUpdate();
    },
    enableFields(enableKeys) {
      enableFields(this.keys, this.fields, this.values, enableKeys);

      for (const key of Object.keys(this.keys)) {
        const { field } = this.keys[key];
        if (this.fields[field]) {
          this.$set(this.fields[field], 'disabled', this.fields[field].disabled);
          this.$set(this.fields[field], 'selectOptions', { ...this.fields[field].selectOptions });
          this.$set(this.fields[field], 'placeholder', this.fields[field].placeholder);
        }
      }
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