<template>
    <panel :header="header">
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
import {disableFields, enableFields} from "@/helpers/formFieldUtils";
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
    reloads: {
      type: Array,
      default: () => {return []},
    },
    values: {
      type: Array,
      default: () => {return []},
    },
  },

  data() {
        return {
            schema: {
                fields: {
                    incipit: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Incipit',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'incipit',
                        required: true,
                        validator: validatorUtil.string,
                    },
                    title_GR: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Greek title',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'title_GR',
                        validator: validatorUtil.string,
                    },
                    title_LA: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Latin title',
                        labelClasses: 'control-label',
                        model: 'title_LA',
                        validator: validatorUtil.string,
                    },
                },
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
  }
}
</script>
