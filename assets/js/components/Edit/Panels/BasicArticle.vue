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
  createMultiSelect,
  dependencyField, disableFields,
  enableField,
} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

export default {

    props: {
        values: {
            type: Object,
            default: () => {return {}}
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    journals: {field: 'journal', init: false},
                    journalIssues: {field: 'journalIssue', init: false},
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
        reloads: {
          type: Array,
          default: () => {return []},
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
                    journal: createMultiSelect(
                        'Journal',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                    journalIssue: createMultiSelect(
                        'Journal Issue',
                        {
                            model: 'journalIssue',
                            dependency: 'journal',
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                    startPage: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Start Page',
                        labelClasses: 'control-label',
                        model: 'startPage',
                        validator: [VueFormGenerator.validators.number, this.startBeforeEndValidator, this.endWithoutStartValidator],
                    },
                    endPage: {
                        type: 'input',
                        inputType: 'number',
                        label: 'End Page',
                        labelClasses: 'control-label',
                        model: 'endPage',
                        validator: [VueFormGenerator.validators.number, this.startBeforeEndValidator, this.endWithoutStartValidator],
                    },
                    rawPages: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Raw Pages',
                        labelClasses: 'control-label',
                        model: 'rawPages',
                        validator: VueFormGenerator.validators.number,
                        disabled: true,
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
        'model.journal'() {
            this.journalChange();
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
      validate() {
        this.$refs.form.validate()
      },
        enableFields(enableKeys) {
            if (enableKeys != null) {
                if (enableKeys.includes('journals')) {
                    this.fields.journal.values = this.values.journals;
                    enableField(this.fields.journal);
                    this.journalChange();
                } else if (enableKeys.includes('journalIssues')) {
                    this.fields.journalIssue.values = this.values.journalIssues;
                    enableField(this.fields.journalIssue);
                    this.journalChange();
                }
            }
        },
        disableFields(disableKeys) {
          disableFields(this.keys, this.fields, disableKeys);
        },
        journalChange() {
            if (this.values.journalIssues.length === 0) {
                return;
            }
            if (this.model.journal == null) {
                dependencyField(this.schema.fields.journalIssue, this.model)
            } else {
                this.schema.fields.journalIssue.values = this.values.journalIssues.filter((journalIssue) => journalIssue.journalId === this.model.journal.id);
                enableField(this.schema.fields.journalIssue)
            }
        },
        startBeforeEndValidator() {
            if (this.model.startPage != null && this.model.endPage != null) {
                if (this.model.startPage > this.model.endPage) {
                    return ['End page must be larger than start page.'];
                }
            }
            return [];
        },
        endWithoutStartValidator() {
            if (this.model.startPage == null && this.model.endPage != null) {
                return ['If an end page is defined, a start page must be defined as well.'];
            }
            return [];
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
    },
}
</script>
