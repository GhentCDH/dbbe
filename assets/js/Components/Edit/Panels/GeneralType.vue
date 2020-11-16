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
        keys: {
            type: Object,
            default: () => {
                return {
                    acknowledgements: {field: 'acknowledgements', init: true},
                    textStatuses: {field: 'textStatus', init: true},
                    criticalStatuses: {field: 'criticalStatus', init: true},
                    occurrences: {field: 'basedOn', init: false},
                };
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    criticalApparatus: {
                        type: 'textArea',
                        label: 'Critical apparatus',
                        labelClasses: 'control-label',
                        model: 'criticalApparatus',
                        rows: 4,
                        validator: VueFormGenerator.validators.string,
                    },
                    acknowledgements: this.createMultiSelect(
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
                    textStatus: this.createMultiSelect(
                        'Text Status',
                        {
                            model: 'textStatus',
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    criticalStatus: this.createMultiSelect(
                        'Editorial Status',
                        {
                            model: 'criticalStatus',
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    basedOn: this.createMultiSelect(
                        'Based On (occurrence)',
                        {
                            model: 'basedOn',
                            styleClasses: 'greek',
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                            internalSearch: false,
                            onSearch: this.greekSearch,
                        }
                    ),
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
    methods: {
        greekSearch(searchQuery) {
            this.schema.fields.basedOn.values = this.schema.fields.basedOn.originalValues.filter(
                option => this.removeGreekAccents(`${option.id} - ${option.name}`).includes(this.removeGreekAccents(searchQuery))
            );
        },
    },
}
</script>
