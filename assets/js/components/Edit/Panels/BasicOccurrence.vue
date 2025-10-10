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

import VueMultiselect from 'vue-multiselect'

import {
  createMultiSelect, disableFields, enableFields,

} from '@/helpers/formFieldUtils';
import Panel from '../Panel'
import validatorUtil from "@/helpers/validatorUtil";
import {calcChanges} from "@/helpers/modelChangeUtil";


export default {
   
    props: {
        keys: {
            type: Object,
            default: () => {
                return {manuscripts: {field: 'manuscript', init: false}};
            },
        },
        header: {
          type: String,
          default: '',
        },
        links: {
          type: Array,
          default: () => {return []},
        },
        model: {
          type: Object,
          default: () => {return {}},
        },
        reloads: {
          type: Array,
          default: () => {return []},
        },
        values: {
          type: Array,
          default: () => {return []},
        },

    },
    data() {
        return {
            schema: {
                groups: [
                    {
                        legend: 'Manuscript',
                        fields: {
                            manuscript: createMultiSelect(
                                'Manuscript',
                                {
                                    required: true,
                                    validator: validatorUtil.required,
                                },
                                {
                                    customLabel: ({id, name}) => {
                                        return `${id} - ${name}`
                                    },
                                }
                            ),
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
                                validator: [validatorUtil.string, this.validateFoliumAndRecto, this.validateFoliumEndWithoutStart, this.validateFoliumOrPages],
                            },
                            foliumStartRecto: this.createRectoRadio('foliumStartRecto', 'Folium start recto'),
                            foliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Folium end',
                                labelClasses: 'control-label',
                                model: 'foliumEnd',
                                validator: [validatorUtil.string, this.validateFoliumAndRecto, this.validateFoliumEndWithoutStart, this.validateFoliumOrPages],
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
                                validator: [validatorUtil.string, this.validateFoliumOrPages],
                            },
                            pageEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Page end',
                                labelClasses: 'control-label',
                                model: 'pageEnd',
                                validator: [validatorUtil.string, this.validateFoliumOrPages],
                            },
                            generalLocation: {
                                type: 'input',
                                inputType: 'text',
                                label: 'General location',
                                labelClasses: 'control-label',
                                model: 'generalLocation',
                                validator: validatorUtil.string,
                            },
                            oldLocation: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Old location',
                                labelClasses: 'control-label',
                                model: 'oldLocation',
                                disabled: true,
                            },
                        },
                    },
                    {
                        legend: 'Alternative location',
                        fields: {
                            alternativeFoliumStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Alternative folium start',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumStart',
                                validator:[validatorUtil.string, this.validateFoliumAndRecto, this.validateAlternativeFoliumEndWithoutStart, this.validateAternativeFoliumOrPages],
                            },
                            alternativeFoliumStartRecto: this.createRectoRadio('alternativeFoliumStartRecto', 'Alternative folium start recto'),
                            alternativeFoliumEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Alternative folium end',
                                labelClasses: 'control-label',
                                model: 'alternativeFoliumEnd',
                                validator:[validatorUtil.string, this.validateFoliumAndRecto, this.validateAlternativeFoliumEndWithoutStart, this.validateAternativeFoliumOrPages],
                            },
                            alternativeFoliumEndRecto: this.createRectoRadio('alternativeFoliumEndRecto', 'Alternative folium end recto'),
                            alternativePageStart: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Alternative page start',
                                labelClasses: 'control-label',
                                model: 'alternativePageStart',
                                validator: [validatorUtil.string, this.validateAternativeFoliumOrPages],
                            },
                            alternativePageEnd: {
                                type: 'input',
                                inputType: 'text',
                                label: 'Alternative page end',
                                labelClasses: 'control-label',
                                model: 'alternativePageEnd',
                                validator: [validatorUtil.string, this.validateAternativeFoliumOrPages],
                            },
                        },
                    },
                ]
            },
            changes: [],
            formOptions: {
              validateAfterChanged: true,
              validationErrorClass: 'has-error',
              validationSuccessClass: 'success',
            },
            isValid: true,
            originalModel: {}
        }
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.schema.groups[0].fields,
                this.schema.groups[1].fields,
                this.schema.groups[2].fields,
            )
        }
    },
    watch: {
        'model.foliumStart'(val) {
            if (val == null || val === '') {
                this.model.foliumStart = null;
                this.model.foliumStartRecto = null;
            }
            this.validate();
        },
        'model.foliumStartRecto'() {
            this.validate();
        },
        'model.foliumEnd'(val) {
            if (val == null || val === '') {
                this.model.foliumEnd = null;
                this.model.foliumEndRecto = null;
            }
            this.validate();
        },
        'model.foliumEndRecto'() {
            this.validate();
        },
        'model.pageStart'(val) {
            if (val === '') {
                this.model.pageStart = null;
            }
            this.validate();
        },
        'model.pageEnd'(val) {
            if (val === '') {
                this.model.pageEnd = null;
            }
            this.validate();
        },
        'model.alternativeFoliumStart'(val) {
            if (val == null || val === '') {
                this.model.alternativeFoliumStart = null;
                this.model.alternativeFoliumStartRecto = null;
            }
            this.validate();
        },
        'model.alternativeFoliumStartRecto'() {
            this.validate();
        },
        'model.alternativeFoliumEnd'(val) {
            if (val == null || val === '') {
                this.model.alternativeFoliumEnd = null;
                this.model.alternativeFoliumEndRecto = null;
            }
            this.validate();
        },
        'model.alternativeFoliumEndRecto'() {
            this.validate();
        },
        'model.alternativePageStart'(val) {
            if (val === '') {
                this.model.alternativePageStart = null;
            }
            this.validate();
        },
        'model.alternativePageEnd'(val) {
            if (val === '') {
                this.model.alternativePageEnd = null;
            }
            this.validate();
        },
    },
    methods: {
        init() {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        this.calcChanges()
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
                validator: this.validateFoliumAndRecto,
            }
        },
        validateFoliumAndRecto(value, field, model) {
            let folium = null;
            let recto = null;
            if (field.model.substr(field.model.length -5) === 'Recto') {
                folium = model[field.model.substr(0, field.model.length - 5)];
                recto = value;
            } else {
                folium = value;
                recto = model[field.model + 'Recto'];
            }
            if (folium != null && recto == null) {
                return ['The recto field is required if the folium field is used.'];
            }
            if (folium == null && recto != null ) {
                return ['The recto field cannot be used if the folium field is unused.']
            }
            return [];
        },
        validateFoliumEndWithoutStart(value, field, model) {
            if (model.foliumStart == null && model.foliumEnd != null) {
                return ['The folium start field is required if a folium end is selected.'];
            }
            return [];
        },
        validateAlternativeFoliumEndWithoutStart(value, field, model) {
            if (model.foliumStart == null && model.foliumEnd != null) {
                return ['The alternative folium start field is required if an alternative folium end is selected.'];
            }
            return [];
        },
        validateFoliumOrPages(value, field, model) {
            if (
                (model.foliumStart != null || model.foliumEnd != null)
                && (model.pageStart != null || model.pageEnd != null)
            ) {
                return ['Folium and pages fields cannot be used simultaneously.'];
            }
            return [];
        },
        validateAternativeFoliumOrPages(value, field, model) {
            if (
                (model.alternativeFoliumStart || model.alternativeFoliumEnd)
                && (model.alternativePageStart || model.alternativePageEnd)
            ) {
                return ['Folium and pages fields cannot be used simultaneously.'];
            }
            return [];
        },

        reload(type) {
          if (!this.reloads.includes(type)) {
            this.$emit('reload', type);
          }
        },
        disableFields(disableKeys) {
          disableFields(this.keys, this.fields, disableKeys);
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
        },
        validate() {
          this.$refs.form.validate()
        },
        enableFields(enableKeys) {
          enableFields(this.keys, this.fields, this.values, enableKeys);
          this.validate();
      },

    }
}
</script>
