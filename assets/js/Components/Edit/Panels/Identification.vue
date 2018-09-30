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

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

VueFormGenerator.validators.identification = function(value, field, model) {
    if (value == null || value === '') {
        return []
    }
    let re = new RegExp(field.pattern)
    if (!re.test(value)) {
        return ['Invalid value']
    }
    if (field.volumes > 1) {
        let array = value.split(', ')
        let volumeArray = []
        for (let identification of array) {
            let volume = identification.split('.')[0]
            if (volumeArray.includes(volume)) {
                return ['Duplicate entry for volume ' + volume]
            }
            else {
                volumeArray.push(volume)
            }
        }
    }
    return []
}

VueFormGenerator.validators.identificationExtra = function(value, field, model) {
    let base = field.model.includes('_extra') ? field.model.replace('_extra', '') : field.model
    let extra = base + '_extra'
    if ((model[extra] != null && model[extra] !== '') && (model[base] == null || model[base] === '')) {
        return ['The base field needs to be set if you want to define an extra.']
    }
    
    return []
}

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        identifiers: {
            type: Array,
            default: () => {return []}
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        let data = {
            schema: {
                fields: {},
            },
        }
        for (let identifier of this.identifiers) {
            data.schema.fields[identifier.systemName] = {
                type: 'input',
                inputType: 'text',
                label: identifier.name,
                labelClasses: 'control-label',
                model: identifier.systemName,
                validator: identifier.extra ?
                    [VueFormGenerator.validators.identification, VueFormGenerator.validators.identificationExtra] :
                    VueFormGenerator.validators.identification,
                pattern: identifier.regex,
                volumes: identifier.volumes,
            }
            if (identifier.extra) {
                data.schema.fields[identifier.systemName + '_extra'] = {
                    type: 'input',
                    inputType: 'text',
                    label: identifier.name + ' extra',
                    labelClasses: 'control-label',
                    model: identifier.systemName + '_extra',
                    validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.identificationExtra],
                }
            }
            if (identifier.description != null) {
                data.schema.fields[identifier.systemName].hint = identifier.description
            }
        }
        return data
    },
}
</script>
