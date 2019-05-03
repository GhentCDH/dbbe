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
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
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
                    journal: this.createMultiSelect(
                        'Journal',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                    journalIssue: this.createMultiSelect(
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
        }
    },
    watch: {
        'model.journal'() {
            this.journalChange();
        },
    },
    methods: {
        enableFields(enableKeys) {
            if (enableKeys != null) {
                if (enableKeys.includes('journals')) {
                    this.fields.journal.values = this.values.journals;
                    this.enableField(this.fields.journal);
                    this.journalChange();
                } else if (enableKeys.includes('journalIssues')) {
                    this.fields.journalIssue.values = this.values.journalIssues;
                    this.enableField(this.fields.journalIssue);
                    this.journalChange();
                }
            }
        },
        journalChange() {
            if (this.values.journalIssues.length === 0) {
                return;
            }
            if (this.model.journal == null) {
                this.dependencyField(this.schema.fields.journalIssue)
            } else {
                this.schema.fields.journalIssue.values = this.values.journalIssues.filter((journalIssue) => journalIssue.journalId === this.model.journal.id);
                this.enableField(this.schema.fields.journalIssue)
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
    },
}
</script>
