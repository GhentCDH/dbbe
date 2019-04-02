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

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

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
                groups: [
                    {
                        legend: 'Incipit / Title',
                        fields: {
                            incipit: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Incipit',
                                labelClasses: 'control-label',
                                styleClasses: 'greek',
                                model: 'incipit',
                                required: true,
                                validator: VueFormGenerator.validators.string,
                            },
                            title: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Title',
                                labelClasses: 'control-label',
                                styleClasses: 'greek',
                                model: 'title',
                                validator: VueFormGenerator.validators.string,
                            },
                        },
                    },
                    {
                        legend: 'Manuscript',
                        fields: {
                            manuscript: this.createMultiSelect('Manuscript', {values: this.values, required: true, validator: VueFormGenerator.validators.required}),
                        },
                    },
                    {
                        legend: 'Location in manuscript',
                        fields: {
                            foliumStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium start',
                                labelClasses: 'control-label',
                                model: 'foliumStart',
                                validator: VueFormGenerator.validators.string,
                            },
                            foliumStartRecto: this.createRectoRadio('foliumStartRecto', 'Folium start recto'),
                            foliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium end',
                                labelClasses: 'control-label',
                                model: 'foliumEnd',
                                validator: VueFormGenerator.validators.string,
                            },
                            foliumEndRecto: this.createRectoRadio('foliumEndRecto', 'Folium end recto'),
                            unsure: {
                                type: 'checkbox',
                                label: 'Unsure',
                                model: 'unsure',
                            },
                            pageStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Page start',
                                labelClasses: 'control-label',
                                model: 'pageStart',
                                validator: VueFormGenerator.validators.string,
                            },
                            pageEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Page end',
                                labelClasses: 'control-label',
                                model: 'pageEnd',
                                validator: VueFormGenerator.validators.string,
                            },
                            generalLocation: {
                                type: 'input',
                                inputType: 'text',
                                label: 'General location',
                                labelClasses: 'control-label',
                                model: 'generalLocation',
                                validator: VueFormGenerator.validators.string,
                            },
                        },
                    },
                    {
                        legend: 'Alternative location',
                        fields: {
                            alternativeFoliumStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium start',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumStart',
                                validator: VueFormGenerator.validators.string,
                            },
                            alternativeFoliumStartRecto: this.createRectoRadio('alternativeFoliumStartRecto', 'Alternative folium start recto'),
                            alternativeFoliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium end',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumEnd',
                                validator: VueFormGenerator.validators.string,
                            },
                            alternativeFoliumEndRecto: this.createRectoRadio('alternativeFoliumEndRecto', 'Alternative folium end recto'),
                        },
                    },
                ]
            }
        }
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.schema.groups[0].fields,
                this.schema.groups[1].fields,
                this.schema.groups[2].fields,
                this.schema.groups[3].fields,
            )
        }
    },
    watch: {
        'model.foliumStart'(val) {
            if (val == null || val === '') {
                this.model.foliumStart = null;
                this.model.foliumStartRecto = null;
            }
            this.$refs.form.validate();
        },
        'model.foliumEnd'(val) {
            if (val == null || val === '') {
                this.model.foliumEnd = null;
                this.model.foliumEndRecto = null;
            }
            this.$refs.form.validate();
        },
        'model.alternativeFoliumStart'(val) {
            if (val == null || val === '') {
                this.model.alternativeFoliumStart = null;
                this.model.alternativeFoliumStartRecto = null;
            }
            this.$refs.form.validate();
        },
        'model.alternativeFoliumEnd'(val) {
            if (val == null || val === '') {
                this.model.alternativeFoliumEnd = null;
                this.model.alternativeFoliumEndRecto = null;
            }
            this.$refs.form.validate();
        },
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableField(this.schema.groups[1].fields.manuscript)
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    let oldValue = this.originalModel[key]
                    let newValue = this.model[key]
                    if (['foliumStartRecto', 'foliumEndRecto', 'alternativeFoliumStartRecto', 'alternativeFoliumEndRecto'].indexOf(key) > -1) {
                        oldValue = this.originalModel[key] == null ? null : (this.originalModel[key] ? 'Recto' : 'Verso');
                        newValue = this.model[key] == null ? null : (this.model[key] ? 'Recto' : 'Verso');
                    }
                    this.changes.push({
                        'key': key,
                        'label': this.fields[key].label,
                        'old': oldValue,
                        'new': newValue,
                        'value': this.model[key],
                    })
                }
            }
        },
        createRectoRadio(model, label) {
            return {
                type: 'radios',
                label: label,
                model: model,
                values: [
                    {
                        name: 'Recto',
                        value: true,
                    },
                    {
                        name: 'Verso',
                        value: false,
                    },
                ],
                validator: this.validateRecto,
            }
        },
        validateRecto(value, field, model) {
            let folium = model[field.model.substr(0, "foliumStartRecto".length -5)];
            if (folium != null && value == null) {
                return ['This field is required if a folium is selected.'];
            }
            return [];
        },
    }
}
</script>
