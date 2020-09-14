<template>
    <panel :header="header">
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
                    blogs: {field: 'blog', init: false},
                };
            },
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    blog: this.createMultiSelect(
                        'Blog',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                    ),
                    url: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Main url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: VueFormGenerator.validators.url,
                    },
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    postDate: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Post date',
                        labelClasses: 'control-label',
                        model: 'postDate',
                        validator: VueFormGenerator.validators.regexp,
                        pattern: '^\\d{2}[/]\\d{2}[/]\\d{4}$',
                        help: 'Please use the format "DD/MM/YYYY", e.g. 24/03/2018.',
                    },
                }
            },
        }
    },
    methods: {
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    let change = {
                        'key': key,
                        'label': this.fields[key].label,
                        'old': this.originalModel[key],
                        'new': this.model[key],
                        'value': this.model[key],
                    }
                    if (key === 'postDate') {
                        if (this.model[key] == null || this.model[key] === '') {
                            change['value'] = null
                        } else {
                            change['value'] = this.model[key].substr(6, 4) + '-' + this.model[key].substr(3, 2) + '-' + this.model[key].substr(0, 2)
                        }
                    }
                    this.changes.push(change)
                }
            }
        },
    },
}
</script>
