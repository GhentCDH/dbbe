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
                    extra: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Extra',
                        labelClasses: 'control-label',
                        model: 'extra',
                        validator: VueFormGenerator.validators.string,
                    },
                }
            }
        }
    },
    watch: {
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
    methods: {
        enableFields(enableKeys) {
            if (enableKeys != null && enableKeys.includes('locations')) {
                this.loadLocationField(this.schema.fields.city, this.model.location);
                this.enableField(this.schema.fields.city, this.model.location);
                this.cityChange();
                this.libraryChange();
            }
        },
        disableFields(disableKeys) {
            if (disableKeys.includes('locations')) {
                this.disableField(this.schema.fields.city);
                this.disableField(this.schema.fields.library);
                this.disableField(this.schema.fields.collection);
            }
        },
        cityChange() {
            if (this.values.length === 0) {
                return;
            }
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
            if (this.values.length === 0) {
                return;
            }
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
            if (this.values.length === 0) {
                return;
            }
            if (this.model.location.collection != null && this.model.location.collection.locationId != null) {
                this.model.location.id = this.model.location.collection.locationId
            }
            this.$refs.form.validate()
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null || Object.keys(this.originalModel).length === 0) {
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
            if (this.model.extra !== this.originalModel.extra && !(this.model.extra == null && this.originalModel.extra == null)) {
                this.changes.push({
                    key: 'extra',
                    keyGroup: 'locatedAt',
                    label: 'Extra',
                    old: this.originalModel.extra,
                    new: this.model.extra,
                    value: this.model.extra,
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
