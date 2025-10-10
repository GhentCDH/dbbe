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
import VueFormGenerator from 'vue3-form-generator-legacy'

import {
  createMultiSelect, disableFields, enableFields,
} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
import validatorUtil from "@/helpers/validatorUtil";
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {

    props: {
        keys: {
            type: Object,
            default: () => {
                return {
                    blogs: {field: 'blog', init: false},
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
                    blog: createMultiSelect(
                        'Blog',
                        {
                            required: true,
                            validator: validatorUtil.required
                        },
                    ),
                    url: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Main url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: validatorUtil.url,
                    },
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: validatorUtil.string,
                    },
                    postDate: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Post date',
                        labelClasses: 'control-label',
                        model: 'postDate',
                        validator: validatorUtil.regexp,
                        pattern: '^\\d{2}/\\d{2}/\\d{4}$',
                        help: 'Please use the format "DD/MM/YYYY", e.g. 24/03/2018.',
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
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    let change = {
                        'key': key,
                        'label': this.fields[key].label,
                        'old': this.originalModel[key],
                        'new': this.model[key],
                        'value': this.model[key],
                    }
                    if (key === 'postDate') {
                        if (this.model[key] == null || this.model[key] === '') {
                            change['value'] = null
                        } else {
                            change['value'] = this.model[key].substr(6, 4) + '-' + this.model[key].substr(3, 2) + '-' + this.model[key].substr(0, 2)
                        }
                    }
                    this.changes.push(change)
                }
            }
        },
        init() {
          this.originalModel = JSON.parse(JSON.stringify(this.model));
          this.enableFields();
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
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
    },
}
</script>
