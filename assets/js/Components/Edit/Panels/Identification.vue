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
                validator: VueFormGenerator.validators.identification,
                pattern: identifier.regex,
                volumes: identifier.volumes,
            }
            if (identifier.description != null) {
                data.schema.fields[identifier.systemName].hint = identifier.description
            }
        }
        return data
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
