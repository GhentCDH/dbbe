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
import VueFormGenerator from 'vue-form-generator'


import Panel from '../Panel'
import {calcChanges} from "@/helpers/modelChangeUtil";
import {disableFields, enableField, enableFields} from "@/helpers/formFieldUtils";

Vue.use(VueFormGenerator)
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
      default: () => {return {}},
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
                        validator: VueFormGenerator.validators.string,
                    },
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'year',
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City',
                        labelClasses: 'control-label',
                        model: 'city',
                        validator: VueFormGenerator.validators.string,
                    },
                    institution: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Institution',
                        labelClasses: 'control-label',
                        model: 'institution',
                        validator: VueFormGenerator.validators.string,
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
    watch: {
        'model.year' () {
            if (isNaN(this.model.year)) {
                this.model.year = null;
                this.$nextTick(function() {
                    this.validate();
                });
            }
        },
    },

  computed: {
    fields() {
      return this.schema.fields
    }
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
