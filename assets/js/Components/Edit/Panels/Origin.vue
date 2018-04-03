<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated" />
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import Abstract from '../Abstract'
import Fields from '../../Fields'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [ Abstract, Fields ],
    data() {
        return {
            schema: {
                fields: {
                    origin: this.createMultiSelect('Origin', {values: this.values}, {trackBy: 'id'}),
                }
            }
        }
    },
    watch: {
        values() {
            this.enableField(this.schema.fields.origin)
        },
        model() {
            this.enableField(this.schema.fields.origin)
        }
    },
    methods: {
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
