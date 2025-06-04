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
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [
        AbstractPanelForm,
    ],
    data() {
        return {
            revalidate: false,
            schema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'year',
                        validator: [
                            VueFormGenerator.validators.number,
                            this.yearOrForthcoming,
                        ],
                    },
                    forthcoming: {
                        type: 'checkbox',
                        label: 'Forthcoming',
                        labelClasses: 'control-label',
                        model: 'forthcoming',
                        validator: this.yearOrForthcoming,
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
                    institution: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Institution',
                        labelClasses: 'control-label',
                        model: 'institution',
                        validator: VueFormGenerator.validators.string,
                    },
                    volume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'volume',
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
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
        },
    },
    methods: {
        // Override to make sure forthcoming is set
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model));
            if (this.model.forthcoming == null) {
                this.model.forthcoming = false;
            }
            enableFields();
            this.calcChanges();
        },
        yearOrForthcoming() {
            if (!this.revalidate) {
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
            if (
                (
                    this.model.year == null
                    && this.model.forthcoming === false
                )
                || (
                    this.model.year != null
                    && this.model.forthcoming === true
                )
            ) {
                return ['Exactly one of the fields "Year", "Forthcoming" is required.'];
            }
            return [];
        },
    },
}
</script>
