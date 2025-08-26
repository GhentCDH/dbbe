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
            revalidate: false,
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
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'year',
                        validator: [
                            validatorUtil.number,
                            this.yearOrForthcoming,
                        ],
                    },
                    forthcoming: {
                        type: 'checkbox',
                        label: 'Forthcoming',
                        labelClasses: 'control-label',
                        model: 'forthcoming',
                        validator: this.yearOrForthcoming,
                    },
                    city: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City',
                        labelClasses: 'control-label',
                        model: 'city',
                        required: true,
                        validator: validatorUtil.string,
                    },
                    institution: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Institution',
                        labelClasses: 'control-label',
                        model: 'institution',
                        validator: validatorUtil.string,
                    },
                    volume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'volume',
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
    watch: {
        'model.year' () {
            if (isNaN(this.model.year)) {
                this.model.year = null;
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
        },
    },
  computed: {
    fields() {
      return this.schema.fields
    }
  },
  methods: {
        // Override to make sure forthcoming is set
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model));
            if (this.model.forthcoming == null) {
                this.model.forthcoming = false;
            }
            this.enableFields();
            this.changes = calcChanges(this.model, this.originalModel, this.fields);

        },
        yearOrForthcoming() {
            if (!this.revalidate) {
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
            if (
                (
                    this.model.year == null
                    && this.model.forthcoming === false
                )
                || (
                    this.model.year != null
                    && this.model.forthcoming === true
                )
            ) {
                return ['Exactly one of the fields "Year", "Forthcoming" is required.'];
            }
            return [];
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
