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
import {
  createMultiSelect, disableFields, enableFields,

} from '@/helpers/formFieldUtils';
import {calcChanges} from "@/helpers/modelChangeUtil";

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
                return {origins: {field: 'origin', init: true}};
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    origin: createMultiSelect('Origin'),
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
