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
import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator'


import AbstractPanelForm from '../AbstractPanelForm'

import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [
        AbstractPanelForm,
    ],
    data() {
        return {
            schema: {
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
                    title_GR: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Greek title',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'title_GR',
                        validator: VueFormGenerator.validators.string,
                    },
                    title_LA: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Latin title',
                        labelClasses: 'control-label',
                        model: 'title_LA',
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
        }
    },
}
</script>
