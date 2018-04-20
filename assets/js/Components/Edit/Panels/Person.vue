<template>
    <panel :header="header">
        <vue-form-generator
            :schema="patronsSchema"
            :model="model"
            :options="formOptions"
            ref="patronsForm"
            @validated="validated" />
        <div
            v-if="occurrencePatrons.length > 0"
            class="small">
            <p>Patron(s) provided by occurrences:</p>
            <ul>
                <li
                    v-for="patron in occurrencePatrons"
                    :key="patron.id">
                    {{ patron.name }}
                    <ul>
                        <li
                            v-for="(occurrence, index) in patron.occurrences"
                            :key="index">
                            {{ occurrence }}
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <vue-form-generator
            :schema="scribesSchema"
            :model="model"
            :options="formOptions"
            ref="scribesForm"
            @validated="validated" />
        <div
            v-if="occurrenceScribes.length > 0"
            class="small">
            <p>Scribe(s) provided by occurrences:</p>
            <ul>
                <li
                    v-for="scribe in occurrenceScribes"
                    :key="scribe.id">
                    {{ scribe.name }}
                    <ul>
                        <li
                            v-for="(occurrence, index) in scribe.occurrences"
                            :key="index">
                            {{ occurrence }}
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <vue-form-generator
            :schema="relatedPersonsSchema"
            :model="model"
            :options="formOptions"
            ref="relatedPersonsForm"
            @validated="validated" />
        <div class="small">
            <p>Related persons are persons that are related to this manuscript but that are not a patron or a scribe of the manuscript or of occurrences related to the manuscript.</p>
        </div>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import Abstract from '../Abstract'
import Fields from '../../Fields'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [ Abstract, Fields ],
    props: {
        occurrencePatrons: {
            type: Array,
            default: () => {return []}
        },
        occurrenceScribes: {
            type: Array,
            default: () => {return []}
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            patronsSchema: {
                fields: {
                    patrons: this.createMultiSelect(
                        'Patrons',
                        {values: this.values.patrons},
                        {multiple: true, closeOnSelect: false}
                    ),
                }
            },
            scribesSchema: {
                fields: {
                    scribes: this.createMultiSelect(
                        'Scribes',
                        {values: this.values.scribes},
                        {multiple: true, closeOnSelect: false}
                    ),
                }
            },
            relatedPersonsSchema: {
                fields: {
                    relatedPersons: this.createMultiSelect(
                        'Related Persons',
                        {values: this.values.relatedPersons},
                        {multiple: true, closeOnSelect: false}
                    ),
                }
            },
        }
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.patronsSchema.fields,
                this.scribesSchema.fields,
                this.relatedPersonsSchema.fields
            )
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
            this.enableFields()
        },
        enableFields() {
            this.enableField(this.patronsSchema.fields.patrons)
            this.enableField(this.scribesSchema.fields.scribes)
            this.enableField(this.relatedPersonsSchema.fields.relatedPersons)
        },
        validate() {
            this.$refs.patronsForm.validate()
            this.$refs.scribesForm.validate()
            this.$refs.relatedPersonsForm.validate()
        },
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
