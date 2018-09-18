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
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

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
    data() {
        return {
            schema: {
                groups: [
                    {
                        legend: 'Incipit / Title',
                        fields: {
                            incipit: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Incipit',
                                labelClasses: 'control-label',
                                styleClasses: 'greek',
                                model: 'incipit',
                                required: true,
                                validator: VueFormGenerator.validators.string,
                            },
                            title: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Title',
                                labelClasses: 'control-label',
                                styleClasses: 'greek',
                                model: 'title',
                                validator: VueFormGenerator.validators.string,
                            },
                        },
                    },
                    {
                        legend: 'Manuscript',
                        fields: {
                            manuscript: this.createMultiSelect('Manuscript', {values: this.values, required: true, validator: VueFormGenerator.validators.required}),
                        },
                    },
                    {
                        legend: 'Location',
                        fields: {
                            foliumStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium start',
                                labelClasses: 'control-label',
                                model: 'foliumStart',
                                validator: VueFormGenerator.validators.string,
                            },
                            foliumStartRecto: {
                                type: 'checkbox',
                                label: 'Folium start recto',
                                labelClasses: 'control-label',
                                model: 'foliumStartRecto',
                            },
                            foliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium end',
                                labelClasses: 'control-label',
                                model: 'foliumEnd',
                                validator: VueFormGenerator.validators.string,
                            },
                            foliumEndRecto: {
                                type: 'checkbox',
                                label: 'Folium end recto',
                                labelClasses: 'control-label',
                                model: 'foliumEndRecto',
                            },
                            unsure: {
                                type: 'checkbox',
                                label: 'Unsure',
                                labelClasses: 'control-label',
                                model: 'unsure',
                            },
                            generalLocation: {
                                type: 'input',
                                inputType: 'text',
                                label: 'General location',
                                labelClasses: 'control-label',
                                model: 'generalLocation',
                                validator: VueFormGenerator.validators.string,
                            },
                        },
                    },
                    {
                        legend: 'Alternative location',
                        fields: {
                            alternativeFoliumStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium start',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumStart',
                                validator: VueFormGenerator.validators.string,
                            },
                            alternativeFoliumStartRecto: {
                                type: 'checkbox',
                                label: 'Folium start recto',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumStartRecto',
                            },
                            alternativeFoliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium end',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumEnd',
                                validator: VueFormGenerator.validators.string,
                            },
                            alternativeFoliumEndRecto: {
                                type: 'checkbox',
                                label: 'Folium end recto',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumEndRecto',
                            },
                        },
                    },
                ]
            }
        }
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.schema.groups[0].fields,
                this.schema.groups[1].fields,
                this.schema.groups[2].fields,
                this.schema.groups[3].fields,
            )
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.groups[1].fields.manuscript)
        },
    }
}
</script>
