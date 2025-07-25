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
        values: {
            type: Object,
            default: () => {return {}}
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    acknowledgements: {field: 'acknowledgements', init: true},
                    textStatuses: {field: 'textStatus', init: true},
                    recordStatuses: {field: 'recordStatus', init: true},
                    dividedStatuses: {field: 'dividedStatus', init: true},
                    sourceStatuses: {field: 'sourceStatus', init: true},
                };
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    palaeographicalInfo: {
                        type: 'textArea',
                        label: 'Palaeographical information',
                        labelClasses: 'control-label',
                        model: 'palaeographicalInfo',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    contextualInfo: {
                        type: 'textArea',
                        label: 'Contextual information',
                        labelClasses: 'control-label',
                        model: 'contextualInfo',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    acknowledgements: createMultiSelect(
                        'Acknowledgements',
                        {
                            model: 'acknowledgements',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                    publicComment: {
                        type: 'textArea',
                        label: 'Public comment',
                        labelClasses: 'control-label',
                        model: 'publicComment',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    privateComment: {
                        type: 'textArea',
                        styleClasses: 'has-warning',
                        label: 'Private comment',
                        labelClasses: 'control-label',
                        model: 'privateComment',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    textStatus: createMultiSelect('Text Status', {model: 'textStatus'}),
                    recordStatus: createMultiSelect('Record Status', {model: 'recordStatus'}),
                    dividedStatus: createMultiSelect('Verses correctly divided', {model: 'dividedStatus'}),
                    sourceStatus: createMultiSelect('Source', {model: 'sourceStatus'}),
                    public: {
                        type: 'checkbox',
                        styleClasses: 'has-error',
                        label: 'Public',
                        labelClasses: 'control-label',
                        model: 'public',
                    },
                }
            },
        }
    },
}
</script>
