<template>
    <panel
        :header="header"
        :links="links"
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
import Panel from '../Panel'
import validatorUtil from "@/helpers/validatorUtil";
import {disableFields, enableField} from "@/helpers/formFieldUtils";
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
      keys: {
        type: Object,
        default: () => {return {}},
      },
    },
    data() {
        return {
            schema: {
                fields: {
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
    validated(isValid, errors) {
      this.isValid = isValid
      this.changes = calcChanges(this.model, this.originalModel, this.fields);
      this.$emit('validated', isValid, this.errors, this)
    },
    enableFields(enableKeys) {
      for (let key of Object.keys(this.keys)) {
        if ((this.keys[key].init && enableKeys == null) || (enableKeys != null && enableKeys.includes(key))) {
          if (Array.isArray(this.values)) {
            this.fields[this.keys[key].field].values = this.values;
            this.fields[this.keys[key].field].originalValues = JSON.parse(JSON.stringify(this.values));
          } else {
            this.fields[this.keys[key].field].values = this.values[key];
            this.fields[this.keys[key].field].originalValues = JSON.parse(JSON.stringify(this.values[key]));
          }
          enableField(this.fields[this.keys[key].field], null);
        }
      }
    },
    disableFields(disableKeys) {
      disableFields(this.keys, this.fields, disableKeys);
    },
    validate() {
      this.$refs.form.validate()
    },
    onChange() {
      this.changes = calcChanges(this.model, this.originalModel, this.fields);
      this.$emit('validated')
    },
  }
}
</script>
