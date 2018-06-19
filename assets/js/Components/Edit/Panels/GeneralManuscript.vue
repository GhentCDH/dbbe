<template>
    <panel
        :header="header"
        :link="link">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="form"
            @validated="validated" />
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
    data() {
        return {
            schema: {
                fields: {
                    diktyon: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Diktyon',
                        labelClasses: 'control-label',
                        model: 'diktyon',
                        validator: VueFormGenerator.validators.number,
                    },
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
                    status: this.createMultiSelect('Status', {values: this.values}, {}),
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
    watch: {
        values() {
            this.init()
        },
        model() {
            this.init()
        }
    },
    mounted () {
        this.init()
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.fields.status)
        },
        validated(isValid, errors) {
            // fix NaN
            if (isNaN(this.model.diktyon)) {
                this.model.diktyon = null
                this.$refs.form.validate()
                return
            }
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        },
    }
}
</script>
