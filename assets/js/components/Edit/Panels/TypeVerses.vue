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
        reloads: {
          type: Array,
          default: () => {return []},
        },
        values: {
            type: Object,
            default: () => {return {}}
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
                    numberOfVerses: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Number of verses',
                        labelClasses: 'control-label',
                        model: 'numberOfVerses',
                        validator: validatorUtil.number,
                        hint: 'Should be left blank if equal to the number of verses listed below. A "0" (without quotes) should be input when the number of verses is unknown.',
                    },
                    verses: {
                        type: 'textArea',
                        label: 'Verses',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'verses',
                        rows: 12,
                        required: true,
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
        'model.numberOfVerses'() {
            if (isNaN(this.model.numberOfVerses)) {
                this.model.numberOfVerses = null;
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
        validate() {
          this.$refs.form.validate()
        },
        calcChanges() {
            this.changes = []
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    if (key === 'verses') {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.displayVerses(this.originalModel[key]),
                            'new': this.displayVerses(this.model[key]),
                            'value': this.model[key],
                        })
                    }
                    else {
                        this.changes.push({
                            'key': key,
                            'label': this.fields[key].label,
                            'old': this.originalModel[key],
                            'new': this.model[key],
                            'value': this.model[key],
                        })
                    }
                }
            }
        },
        validated(isValid, errors) {
            this.isValid = isValid;
            this.calcChanges();
            this.$emit('validated', isValid, this.errors, this)
        },
        displayVerses(verses) {
            return verses.split('\n').map(verse => '<span class="greek">' + verse + '</span>')
        }
    },
}
</script>
