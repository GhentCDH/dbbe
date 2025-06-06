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
                    statuses: {field: 'status', init: true},
                };
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    acknowledgements: createMultiSelect(
                        'Acknowledgements',
                        {
                            model: 'acknowledgements',
                            values: this.values.acknowledgements,
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
                    status: createMultiSelect('Status', {values: this.values.statuses}, {}),
                    illustrated: {
                        type: 'checkbox',
                        styleClasses: 'has-warning',
                        label: 'Illustrated',
                        labelClasses: 'control-label',
                        model: 'illustrated',
                    },
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
