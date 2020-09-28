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
        keys: {
            type: Object,
            default: () => {
                return {
                    bookClusters: {field: 'bookCluster', init: false},
                    bookSeriess: {field: 'bookSeries', init: false},
                };
            },
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    bookCluster: this.createMultiSelect('Book cluster', {model: 'bookCluster', validator: this.validateClusterOrTitle}),
                    volume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Book cluster volume',
                        labelClasses: 'control-label',
                        model: 'volume',
                        validator: VueFormGenerator.validators.string,
                    },
                    totalVolumes: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Book cluster total Volumes',
                        labelClasses: 'control-label',
                        model: 'totalVolumes',
                        validator: VueFormGenerator.validators.number,
                    },
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        validator: this.validateClusterOrTitle,
                    },
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'year',
                        required: true,
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City',
                        labelClasses: 'control-label',
                        model: 'city',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    editor: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Editor',
                        labelClasses: 'control-label',
                        model: 'editor',
                        validator: VueFormGenerator.validators.string,
                        disabled: true,
                    },
                    publisher: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Publisher',
                        labelClasses: 'control-label',
                        model: 'publisher',
                        validator: VueFormGenerator.validators.string,
                    },
                    bookSeries: this.createMultiSelect('Book series', {model: 'bookSeries'}),
                    seriesVolume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Series volume',
                        labelClasses: 'control-label',
                        model: 'seriesVolume',
                        validator: VueFormGenerator.validators.string,
                    },
                }
            },
        }
    },
    watch: {
        'model.year' () {
            if (isNaN(this.model.year)) {
                this.model.year = null;
                this.$nextTick(function() {
                    this.validate();
                });
            }
        },
        'model.totalVolumes' () {
            if (isNaN(this.model.totalVolumes)) {
                this.model.totalVolumes = null;
                this.$nextTick(function() {
                    this.validate();
                });
            }
        },
        // reset title to null if nothing is entered
        'model.title' () {
            if (this.model.title === '') {
                this.model.title = null
            }
            this.validate()
        },
    },
    methods: {
        validateClusterOrTitle() {
            if (this.model.bookCluster == null && this.model.title == null) {
                return ['Please provide at least a cluster or a title.']
            }
            return []
        },
    },
}
</script>
