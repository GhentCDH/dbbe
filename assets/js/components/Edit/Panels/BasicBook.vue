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
import VueFormGenerator from 'vue3-form-generator-legacy'

import {
  createMultiSelect, disableFields, enableFields,
} from '@/helpers/formFieldUtils';
import {calcChanges} from "@/helpers/modelChangeUtil";
import validatorUtil from "@/helpers/validatorUtil";


export default {

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
    },
    data() {
        return {
            revalidate: false,
            schema: {
                fields: {
                    bookCluster: createMultiSelect(
                        'Book cluster',
                        {
                            model: 'bookCluster',
                            validator: this.validateClusterOrTitle,
                        },
                    ),
                    volume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Book cluster volume',
                        labelClasses: 'control-label',
                        model: 'volume',
                        validator: validatorUtil.string,
                    },
                    totalVolumes: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Book cluster total Volumes',
                        labelClasses: 'control-label',
                        model: 'totalVolumes',
                        validator: validatorUtil.number,
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
                    bookSeries: createMultiSelect(
                        'Book series',
                        {
                            model: 'bookSeries',
                        },
                    ),
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
    watch: {
        'model.year' () {
            if (isNaN(this.model.year)) {
                this.model.year = null;
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
        },
        'model.totalVolumes' () {
            if (isNaN(this.model.totalVolumes)) {
                this.model.totalVolumes = null;
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
        },
        // reset title to null if nothing is entered
        'model.title' () {
            if (this.model.title === '') {
                this.model.title = null
            }
            this.revalidate = true;
            this.validate();
            this.revalidate = false;
        },
    },
  computed: {
    fields() {
      return this.schema.fields
    }
  },

  methods: {
        // Override to make sure forthcoming is set
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model));
            if (this.model.forthcoming == null) {
                this.model.forthcoming = false;
            }
            this.enableFields();
            calcChanges();
        },
        validateClusterOrTitle() {
            if (!this.revalidate) {
                this.revalidate = true;
                this.validate();
                this.revalidate = false;
            }
            if (this.model.bookCluster == null && this.model.title == null) {
                return ['Please provide at least a cluster or a title.'];
            }
            return [];
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
      validated(isValid, errors) {
        this.isValid = isValid
        this.changes = calcChanges(this.model, this.originalModel, this.fields);
        this.$emit('validated', isValid, this.errors, this)
      },
      reload(type) {
        if (!this.reloads.includes(type)) {
          this.$emit('reload', type);
        }
      },
      disableFields(disableKeys) {
        disableFields(this.keys, this.fields, disableKeys);
      },
      enableFields(enableKeys) {
        enableFields(this.keys, this.fields, this.values, enableKeys);
      },
      validate() {
        this.$refs.form.validate()
      },
    },
}
</script>
