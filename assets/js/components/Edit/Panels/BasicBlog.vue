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

import {calcChanges} from "@/helpers/modelChangeUtil";
import {disableFields, enableFields} from "@/helpers/formFieldUtils";
import validatorUtil from "@/helpers/validatorUtil";


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
  computed: {
    fields() {
      return this.schema.fields
    }
  },

  data() {
        return {
            schema: {
                fields: {
                    url: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Main url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: validatorUtil.string,
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
                    lastAccessed: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Last accessed',
                        labelClasses: 'control-label',
                        model: 'lastAccessed',
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
                    if (key === 'lastAccessed') {
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
        validated(isValid, errors) {
        this.isValid = isValid
        this.changes = calcChanges(this.model, this.originalModel, this.fields);
        this.$emit('validated', isValid, this.errors, this)
      },

    },
}
</script>
