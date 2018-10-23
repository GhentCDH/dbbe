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
