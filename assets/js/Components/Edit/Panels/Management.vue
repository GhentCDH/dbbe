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
import Vue from 'vue/dist/vue.js';
import VueFormGenerator from 'vue-form-generator'


import AbstractPanelForm from '../../../mixins/AbstractPanelForm'
import {
  createMultiSelect,

} from '@/helpers/formFieldUtils';
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [
        AbstractPanelForm,
    ],
    props: {
        keys: {
            type: Object,
            default: () => {
                return {managements: {field: 'managements', init: true}};
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    managements: createMultiSelect(
                        'Management collection',
                        {
                            model: 'managements',
                            values: this.values,
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
}
</script>
