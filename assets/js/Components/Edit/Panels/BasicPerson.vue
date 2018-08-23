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

VueFormGenerator.validators.name = function(value, field, model) {
    if (
        (model.firstName == null || model.firstName === '')
        && (model.lastName == null || model.lastName === '')
        && (model.extra == null || model.extra === '')
        && (model.unprocessed == null || model.unprocessed === '')
    ) {
        return ['At least one of the fields "First Name", "Last Name", "Extra" is required.']
    }

    return []
}

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    data() {
        return {
            schema: {
                fields: {
                    firstName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'First Name',
                        labelClasses: 'control-label',
                        model: 'firstName',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                    },
                    lastName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Last Name',
                        labelClasses: 'control-label',
                        model: 'lastName',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                    },
                    extra: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Extra',
                        labelClasses: 'control-label',
                        model: 'extra',
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.name],
                    },
                    unprocessed: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Unprocessed',
                        labelClasses: 'control-label',
                        model: 'unprocessed',
                        disabled: true,
                    },
                    historical: {
                        type: 'checkbox',
                        label: 'Historical',
                        labelClasses: 'control-label',
                        model: 'historical',
                    },
                    modern: {
                        type: 'checkbox',
                        label: 'Modern',
                        labelClasses: 'control-label',
                        model: 'modern',
                    },
                }
            },
        }
    },
}
</script>
