<template>
    <panel
        :header="header"
        :link="link"
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
                    keywords: this.createMultiSelect(
                        'Keywords',
                        {
                            values: this.values
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                }
            }
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.fields.keywords)
        },
    }
}
</script>
