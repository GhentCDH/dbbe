<template>
    <panel
        :header="header"
        :links="links"
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
import Vue from 'vue/dist/vue.js';
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
                }
            },
        }
    },
}
</script>
