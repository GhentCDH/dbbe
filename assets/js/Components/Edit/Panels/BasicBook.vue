<template>
    <panel :header="header">
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
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'year',
                        required: true,
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City',
                        labelClasses: 'control-label',
                        model: 'city',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    editor: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Editor',
                        labelClasses: 'control-label',
                        model: 'editor',
                        validator: VueFormGenerator.validators.string,
                    },
                    publisher: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Publisher',
                        labelClasses: 'control-label',
                        model: 'publisher',
                        validator: VueFormGenerator.validators.string,
                    },
                    series: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Series',
                        labelClasses: 'control-label',
                        model: 'series',
                        validator: VueFormGenerator.validators.string,
                    },
                    volume: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'volume',
                        validator: VueFormGenerator.validators.number,
                    },
                    totalVolumes: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Total Volumes',
                        labelClasses: 'control-label',
                        model: 'totalVolumes',
                        validator: VueFormGenerator.validators.number,
                    },
                }
            },
        }
    },
}
</script>
