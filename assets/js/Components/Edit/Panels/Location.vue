<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated" />
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import Abstract from '../Abstract'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [ Abstract ],
    data() {
        return {
            schema: {
                fields: {
                    city: this.createMultiSelect('City', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    library: this.createMultiSelect('Library', {required: true, validator: VueFormGenerator.validators.required, dependency: 'city'}, {trackBy: 'id'}),
                    collection: this.createMultiSelect('Collection', {dependency: 'library'}, {trackBy: 'id'}),
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
            this.initFields()
        },
        model() {
            this.initFields()
        },
        'model.city'() {
            if (this.model.city == null) {
                this.dependencyField(this.schema.fields.library)
            }
            else {
                this.loadLocationField(this.schema.fields.library)
                this.enableField(this.schema.fields.library)
            }
        },
        'model.library'() {
            if (this.model.library == null) {
                this.dependencyField(this.schema.fields.collection)
            }
            else {
                this.loadLocationField(this.schema.fields.collection)
                this.enableField(this.schema.fields.collection)
            }
        },
    },
    methods: {
        initFields() {
            this.loadLocationField(this.schema.fields.city)
            this.enableField(this.schema.fields.city)
            this.loadLocationField(this.schema.fields.library)
        },
        loadLocationField(field) {
            let locations = Object.values(this.values)
            // filter dependency
            if (field.hasOwnProperty('dependency') && this.model[field.dependency] != null) {
                locations = locations.filter((location) => location[field.dependency + '_id'] === this.model[field.dependency]['id'])
            }
            // filter null values
            locations = locations.filter((location) => location[field.model + '_id'] != null)

            let values = locations
                // get the requested field information
                .map((location) => {return {'id': location[field.model + '_id'], 'name': location[field.model + '_name']}})
                // remove duplicates
                .filter((location, index, self) => index === self.findIndex((l) => l.id === location.id))

            field.values = values
        },
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
