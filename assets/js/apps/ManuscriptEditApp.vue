<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <h2>Edit Manuscript</h2>
            <!--TODO: manage locations outside of manuscripts link-->
            <alert v-for="(item, index) in alerts" :key="item.key" :type="item.type" dismissible @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
            <vue-form-generator :schema="locationSchema" :model="model" :options="formOptions" ref="locationForm" @onValidated="validated()"></vue-form-generator>
            <btn type="warning" :disabled="noNewValues" @click="resetModal=true">Reset</btn>
            <btn type="success" :disabled="noNewValues || invalidForms" @click="calcDiff();saveModal=true">Save changes</btn>
            <div class="loading-overlay" v-if="this.openRequests">
                <div class="spinner">
                </div>
            </div>
        </article>
        <modal v-model="resetModal" title="Reset manuscript" auto-focus>
            <p>Are you sure you want to reset the manuscript information?</p>
            <div slot="footer">
                <btn @click="resetModal=false">Cancel</btn>
                <btn type="danger" @click="reset()" data-action="auto-focus">Reset</btn>
            </div>
        </modal>
        <modal v-model="saveModal" title="Save manuscript" auto-focus>
            <p>Are you sure you want to save this manuscript information?</p>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Previous value</th>
                        <th>New value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in diff">
                        <td>{{ row['label'] }}</td>
                        <td>{{ row['old'] }}</td>
                        <td>{{ row['new'] }}</td>
                    </tr>
                </tbody>
            </table>
            <div slot="footer">
                <btn @click="saveModal=false">Cancel</btn>
                <btn type="success" @click="save()" data-action="auto-focus">Save</btn>
            </div>
        </modal>
    </div>
</template>

<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import * as uiv from 'uiv'
    import VueMultiselect from 'vue-multiselect'
    import fieldMultiselectClear from '../components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)
    Vue.use(uiv)

    Vue.component('multiselect', VueMultiselect)
    Vue.component('fieldMultiselectClear', fieldMultiselectClear)

    var YEAR_MIN = 1
    var YEAR_MAX = (new Date()).getFullYear()

    export default {
        props: [
            'getManuscriptUrl',
            'putManuscriptUrl',
            'manuscript',
            'locations'
        ],
        data() {
            return {
                model: {
                    city: null,
                    library: null,
                    collection: null,
                    shelf: null
                },
                locationSchema: {
                    fields: {
                        city: this.createMultiSelect('City', {required: true, validator: VueFormGenerator.validators.required}),
                        library: this.createMultiSelect('Library', {required: true, validator: VueFormGenerator.validators.required, dependency: 'city'}),
                        collection: this.createMultiSelect('Collection', {dependency: 'library'}),
                        shelf: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Shelf Number',
                            model: 'shelf',
                            required: true,
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true,
                    validationErrorClass: "has-error",
                    validationSuccessClass: "success"
                },
                openRequests: 0,
                alerts: [],
                originalModel: {},
                diff:[],
                resetModal: false,
                saveModal: false,
                invalidForms: false
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.loadManuscript(JSON.parse(this.manuscript))
            })
        },
        computed: {
            noNewValues() {
                return JSON.stringify(this.originalModel) === JSON.stringify(this.model)
            }
        },
        watch: {
            'model.city': function(newValue, oldValue) {
                if (newValue === undefined || newValue === null) {
                    this.dependencyField(this.locationSchema.fields.library)
                }
                else {
                    this.loadList(this.locationSchema.fields.library)
                }
            },
            'model.library': function(newValue, oldValue) {
                if (newValue === undefined || newValue === null) {
                    this.dependencyField(this.locationSchema.fields.collection)
                }
                else {
                    this.loadList(this.locationSchema.fields.collection)
                }
            }
        },
        methods: {
            createMultiSelect(label, extra) {
                let result = {
                    type: 'multiselectClear',
                    label: label,
                    placeholder: 'Loading',
                    model: label.toLowerCase(),
                    // Values will be loaded using ajax request
                    values: [],
                    selectOptions: {
                        customLabel: ({id, name}) => {
                            return name
                        },
                        showLabels: false,
                        loading: true
                    },
                    // Will be enabled when list of scribes is loaded
                    disabled: true
                }
                if (extra !== undefined) {
                    for (let key of Object.keys(extra)) {
                        result[key] = extra[key]
                    }
                }
                return result
            },
            loadManuscript(data) {
                this.model.city = data.location.city
                this.model.library = data.location.library
                this.model.collection = data.location.collection
                this.model.shelf = data.location.shelf

                this.$refs.locationForm.validate()

                this.originalModel = Object.assign({}, this.model)
                if (this.locationSchema.fields.city.values.length === 0) {
                    this.loadList(this.locationSchema.fields.city)
                }
            },
            loadList(field) {
                let locations = Object.values(JSON.parse(this.locations))
                if (field.hasOwnProperty('dependency')) {
                    locations = locations.filter((location) => location[field.dependency + '_id'] === this.model[field.dependency]['id'])
                }

                let values = locations
                    .map((location) => {return {'id': location[field.model + '_id'], 'name': location[field.model + '_name']}})
                    // remove duplicates
                    .filter((location, index, self) => index === self.findIndex((l) => l.id === location.id))


                // only keep current value if it is in the list of possible values
                if (this.model[field.model] !== undefined && this.model[field.model] !== null) {
                    if ((values.filter(v => v.id === this.model[field.model].id)).length === 0) {
                        this.model[field.model] = null
                    }
                }
                this.enableField(field, values)
            },
            validated(isValid, errors) {
                this.invalidForms = (
                    !this.$refs.hasOwnProperty('locationForm') || this.$refs.locationForm.errors.length > 0
                )
            },
            disableField(field) {
                field.disabled = true
                field.placeholder = 'Loading'
                field.selectOptions.loading = true
                field.values = []
            },
            dependencyField(field) {
                this.model[field.model] = null
                field.disabled = true
                field.selectOptions.loading = false
                field.placeholder = 'Please select a ' + field.dependency + ' first'
            },
            noValuesField(field) {
                this.model[field.model] = null
                field.disabled = true
                field.selectOptions.loading = false
                field.placeholder = 'No ' + field.label.toLowerCase() + 's available'
            },
            enableField(field, values) {
                if (values.length === 0) {
                    return this.noValuesField(field)
                }

                field.values = values
                field.selectOptions.loading = false
                field.disabled = false
                let label = field.label.toLowerCase()
                field.placeholder = (['origin'].indexOf(label) < 0 ? 'Select a ' : 'Select an ') + label
            },
            sortByName(a, b) {
                if (a.name < b.name) {
                    return -1
                }
                if (a.name > b.name) {
                    return 1
                }
                return 0
            },
            calcDiff() {
                this.diff = []
                let fields = Object.assign({}, this.locationSchema.fields)
                for (let key of Object.keys(this.model)) {
                    if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])) {
                        this.diff.push({
                            'label': fields[key]['label'],
                            'old': this.getValue(this.originalModel[key]),
                            'new': this.getValue(this.model[key]),
                        })
                    }
                }
            },
            getValue(modelField) {
                if (
                    modelField === undefined
                    || modelField === null
                ) {
                    return null
                }
                else if (modelField.hasOwnProperty('name')) {
                    return modelField['name']
                }
                return modelField
            },
            toSave() {
                let result = {}
                for (let key of Object.keys(this.model)) {
                    if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])) {
                        result[key] = this.model[key]
                    }
                }
                return result
            },
            reset() {
                this.resetModal = false
                this.model = Object.assign({}, this.originalModel)
            },
            save() {
                this.openRequests++
                this.saveModal = false
                axios.put(this.putManuscriptUrl, this.toSave())
                    .then( (response) => {
                        this.loadManuscript(response.data)
                        this.alerts.push({type: 'success', message: 'Manuscript data successfully saved.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.alerts.push({type: 'error', message: 'Something whent wrong while saving the manuscript data.'})
                        this.openRequests--
                    })
            }
        }
    }
</script>
