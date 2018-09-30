<template>
    <panel
        :header="header"
        :link="link"
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
                    translation: {
                        type: 'textArea',
                        label: 'Translation',
                        labelClasses: 'control-label',
                        model: 'translation',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    criticalApparatus: {
                        type: 'textArea',
                        label: 'Critical apparatus',
                        labelClasses: 'control-label',
                        model: 'criticalApparatus',
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
                    textStatus: this.createMultiSelect(
                        'Text Status',
                        {
                            model: 'textStatus',
                            values: this.values.textStatuses,
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    criticalStatus: this.createMultiSelect(
                        'Editorial Status',
                        {
                            model: 'criticalStatus',
                            values: this.values.criticalStatuses,
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    basedOn: this.createMultiSelect(
                        'Based On (occurrence)',
                        {
                            model: 'basedOn',
                            values: this.values.occurrences,
                            styleClasses: 'greek',
                        },
                        {
                            customLabel: ({id, name}) => {
                                return id + ' - ' + name
                            },
                        }
                    ),
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
            this.enableField(this.schema.fields.criticalStatus)
            this.enableField(this.schema.fields.basedOn)
        },
    }
}
</script>
