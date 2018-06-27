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
    props: {
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    types: this.createMultiSelect(
                        'Types',
                        {values: this.values.types},
                        {multiple: true, closeOnSelect: false}
                    ),
                    functions: this.createMultiSelect(
                        'Functions',
                        {values: this.values.functions},
                        {multiple: true, closeOnSelect: false}
                    ),
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
            this.enableField(this.schema.fields.types)
            this.enableField(this.schema.fields.functions)
        },
    }
}
</script>
