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

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        keys: {
            type: Object,
            default: () => {
                return {types: {field: 'types', init: false}};
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    types: this.createMultiSelect(
                        'Types',
                        {
                            styleClasses: 'greek',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            customLabel: ({id, name}) => {
                                return id + ' - ' + name
                            },
                        }
                    ),
                }
            }
        }
    },
}
</script>
