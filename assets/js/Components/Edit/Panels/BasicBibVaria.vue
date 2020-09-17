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
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City',
                        labelClasses: 'control-label',
                        model: 'city',
                        validator: VueFormGenerator.validators.string,
                    },
                    institution: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Institution',
                        labelClasses: 'control-label',
                        model: 'institution',
                        validator: VueFormGenerator.validators.string,
                    },
                }
            },
        }
    },
    watch: {
        'model.year' () {
            if (isNaN(this.model.year)) {
                this.model.year = null;
                this.$nextTick(function() {
                    this.validate();
                });
            }
        },
    },
}
</script>
