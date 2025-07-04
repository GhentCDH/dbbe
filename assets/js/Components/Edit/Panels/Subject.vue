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
        values: {
            type: Object,
            default: () => {return {}}
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    historicalPersons: {field: 'personSubjects', init: false},
                    keywordSubjects: {field: 'keywordSubjects', init: true},
                };
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    personSubjects: createMultiSelect(
                        'Persons',
                        {
                            model: 'personSubjects',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                    keywordSubjects: createMultiSelect(
                        'Keywords',
                        {
                            model: 'keywordSubjects',
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
