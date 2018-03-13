<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="generalForm"
            @validated="validated" />
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import Abstract from '../Abstract'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [ Abstract ],
    data() {
        return {
            schema: {
                fields: {
                    diktyon: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Diktyon',
                        model: 'diktyon',
                        validator: VueFormGenerator.validators.number
                    }
                }
            },
        }
    },
    methods: {
        validated(isValid, errors) {
            // fix NaN
            if (isNaN(this.model.diktyon)) {
                this.model.diktyon = null
                this.$refs.generalForm.validate()
                return
            }
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
