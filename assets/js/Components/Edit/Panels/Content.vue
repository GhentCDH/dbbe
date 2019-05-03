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
        keys: {
            type: Object,
            default: () => {
                return {contents: {field: 'contents', init: true}};
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    contents: this.createMultiSelect('Contents', {}, {multiple: true, closeOnSelect: false}),
                }
            }
        }
    },
}
</script>
