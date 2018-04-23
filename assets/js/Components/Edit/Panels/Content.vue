<template>
    <panel
        :header="header"
        :link="link">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated"
            ref="form" />
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
                    content: this.createMultiSelect('Content', {values: this.values}, {multiple: true, closeOnSelect: false}),
                }
            }
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
            this.enableField(this.schema.fields.content)
        },
        validate() {
            this.$refs.form.validate()
        },
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
