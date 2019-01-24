<template>
    <panel
        :header="header"
        :link="link">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="form"
            @validated="validated" />
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

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
    },
    data() {
        return {
            schema: {
                fields: {
                    palaeographicalInfo: {
                        type: 'textArea',
                        label: 'Palaeographical information',
                        labelClasses: 'control-label',
                        model: 'palaeographicalInfo',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    contextualInfo: {
                        type: 'textArea',
                        label: 'Contextual information',
                        labelClasses: 'control-label',
                        model: 'contextualInfo',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    acknowledgements: this.createMultiSelect(
                        'Acknowledgements',
                        {
                            model: 'acknowledgements',
                            values: this.values.acknowledgements,
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                    publicComment: {
                        type: 'textArea',
                        label: 'Public comment',
                        labelClasses: 'control-label',
                        model: 'publicComment',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    privateComment: {
                        type: 'textArea',
                        styleClasses: 'has-warning',
                        label: 'Private comment',
                        labelClasses: 'control-label',
                        model: 'privateComment',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    textStatus: this.createMultiSelect('Text Status', {model: 'textStatus', values: this.values.textStatuses}),
                    recordStatus: this.createMultiSelect('Record Status', {model: 'recordStatus', values: this.values.recordStatuses}),
                    dividedStatus: this.createMultiSelect('Verses correctly divided', {model: 'dividedStatus', values: this.values.dividedStatuses}),
                    sourceStatus: this.createMultiSelect('Source', {model: 'sourceStatus', values: this.values.sourceStatuses}),
                    public: {
                        type: 'checkbox',
                        styleClasses: 'has-error',
                        label: 'Public',
                        labelClasses: 'control-label',
                        model: 'public',
                    },
                }
            },
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.fields.acknowledgements)
            this.enableField(this.schema.fields.textStatus)
            this.enableField(this.schema.fields.recordStatus)
            this.enableField(this.schema.fields.dividedStatus)
            this.enableField(this.schema.fields.sourceStatus)
        },
    }
}
</script>
