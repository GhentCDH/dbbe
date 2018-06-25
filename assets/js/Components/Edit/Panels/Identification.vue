<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema1"
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
            schema1: {
                fields: {
                    rgk: {
                        type: 'input',
                        inputType: 'text',
                        label: 'RGK',
                        labelClasses: 'control-label',
                        model: 'rgk',
                        validator: VueFormGenerator.validators.regexp,
                        pattern: '^I{1,3}[.][\d]+(?:, I{1,3}[.][\d]+)*$',
                        hint: 'E.g., "I.191, II.252, III.315" (without quotes)',
                    },
                    vgh: {
                        type: 'input',
                        inputType: 'text',
                        label: 'VGH',
                        labelClasses: 'control-label',
                        model: 'vgh',
                        validator: VueFormGenerator.validators.regexp,
                        pattern: '^[\d]+[.][A-Z](?:, [\d]+[.][A-Z])*$',
                        hint: 'E.g., "158.D, 175.B, 203.A" (without quotes)',
                    },
                    pbw: {
                        type: 'input',
                        inputType: 'text',
                        label: 'PBW',
                        labelClasses: 'control-label',
                        model: 'pbw',
                        validator: VueFormGenerator.validators.number,
                    },
                },
            },
        }
    },
    watch: {
        values() {
            this.init()
        },
        model() {
            this.init()
        }
    },
    mounted () {
        this.init()
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
    }
}
</script>
