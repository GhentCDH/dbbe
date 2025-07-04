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
import Vue from 'vue';
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
                return {origins: {field: 'origin', init: true}};
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    origin: createMultiSelect('Origin'),
                }
            }
        }
    },
}
</script>
