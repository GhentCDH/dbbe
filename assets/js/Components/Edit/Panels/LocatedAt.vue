<template>
    <panel
        :header="header"
        :link="link">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated"
            ref="form" />
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
                fields: {
                    city: this.createMultiSelect('City', {model: 'location.regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
                    library: this.createMultiSelect('Library', {model: 'location.institution', required: true, validator: VueFormGenerator.validators.required, dependency: 'regionWithParents', dependencyName: 'city'}),
                    collection: this.createMultiSelect('Collection', {model: 'location.collection', dependency: 'institution', dependencyName: 'library'}),
                    shelf: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Shelf Number',
                        labelClasses: 'control-label',
                        model: 'shelf',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                }
            }
        }
    },
    watch: {
        values() {
            this.init()
        },
        model() {
            this.init()
        },
        'model.location.regionWithParents'() {
            this.cityChange()
        },
        'model.location.institution'() {
            this.libraryChange()
        },
        'model.location.collection'() {
            this.collectionChange()
        },
    },
    mounted() {
        this.init()
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.loadLocationField(this.schema.fields.city, this.model.location)
            this.enableField(this.schema.fields.city, this.model.location)
            this.cityChange()
            this.libraryChange()
        },
        cityChange() {
            if (!this.model.location.regionWithParents || this.model.location.regionWithParents.locationId != null) {
                this.model.location.id = null
            }
            if (this.model.location.regionWithParents == null) {
                this.dependencyField(this.schema.fields.library, this.model.location)
            }
            else {
                this.loadLocationField(this.schema.fields.library, this.model.location)
                this.enableField(this.schema.fields.library, this.model.location)
            }
            this.$refs.form.validate()
        },
        libraryChange() {
            if (this.model.location.institution == null) {
                this.dependencyField(this.schema.fields.collection, this.model.location)
            }
            else {
                this.loadLocationField(this.schema.fields.collection, this.model.location)
                this.enableField(this.schema.fields.collection, this.model.location)
                if (this.model.location.institution.locationId != null && this.model.location.collection == null) {
                    this.model.location.id = this.model.location.institution.locationId
                }
            }
            this.$refs.form.validate()
        },
        collectionChange() {
            if (this.model.location.collection != null && this.model.location.collection.locationId != null) {
                this.model.location.id = this.model.location.collection.locationId
            }
            this.$refs.form.validate()
        },
        validate() {
            this.$refs.form.validate()
        },
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            if (this.model.shelf !== this.originalModel.shelf && !(this.model.shelf == null && this.originalModel.shelf == null)) {
                this.changes.push({
                    key: 'shelf',
                    keyGroup: 'locatedAt',
                    label: 'Shelf',
                    old: this.originalModel.shelf,
                    new: this.model.shelf,
                    value: this.model.shelf,
                })
            }
            if (this.model.location.id !== this.originalModel.location.id && !(this.model.location.id == null && this.originalModel.location.id == null)) {
                this.changes.push({
                    key: 'location',
                    keyGroup: 'locatedAt',
                    label: 'Location',
                    old: this.formatLocation(this.originalModel.location),
                    new: this.formatLocation(this.model.location),
                    value: this.model.location,
                })
            }
        },
        formatLocation(location) {
            if (location.regionWithParents == null || location.institution == null) {
                return ''
            }
            let result = location.regionWithParents.name + ' - ' + location.institution.name
            if (location.collection != null) {
                result += ' - ' + location.collection.name
            }
            return result
        },
    }
}
</script>
