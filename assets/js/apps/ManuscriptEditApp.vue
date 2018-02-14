<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <h2>Edit Manuscript</h2>
            <!--TODO: manage locations outside of manuscripts link-->
            <alert v-for="(item, index) in alerts" :key="item.key" :type="item.type" dismissible @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
            <div class="panel panel-default">
                <div class="panel-heading">Location</div>
                <div class="panel-body">
                    <vue-form-generator :schema="locationSchema" :model="model" :options="formOptions" ref="locationForm" @validated="validated()"></vue-form-generator>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Content</div>
                <div class="panel-body">
                    <vue-form-generator :schema="contentSchema" :model="model" :options="formOptions" ref="contentForm" @validated="validated()"></vue-form-generator>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">People</div>
                <div class="panel-body">
                    <vue-form-generator :schema="patronsSchema" :model="model" :options="formOptions" ref="patronsForm" @validated="validated()"></vue-form-generator>
                    <template v-if="this.manuscript.occurrencePatrons.length > 0">
                        Patron(s) provided by occurrences:
                        <ul>
                            <li v-for="patron in this.manuscript.occurrencePatrons">
                                {{ patron.name }}
                                <ul>
                                    <li v-for="occurrence in patron.occurrences">
                                        {{ occurrence }}
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </template>
                    <vue-form-generator :schema="scribesSchema" :model="model" :options="formOptions" ref="scribesForm" @validated="validated()"></vue-form-generator>
                    <template v-if="this.manuscript.occurrenceScribes.length > 0">
                        Scribe(s) provided by occurrences:
                        <ul>
                            <li v-for="scribe in this.manuscript.occurrenceScribes">
                                {{ scribe.name }}
                                <ul>
                                    <li v-for="occurrence in scribe.occurrences">
                                        {{ occurrence }}
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </template>
                </div>
            </div>
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
                        <template v-for="key in ['old', 'new']">
                            <td v-if="Array.isArray(row[key])">
                                <ul v-if="row[key].length > 0">
                                    <li v-for="item in row[key]">
                                        {{ getDisplay(item) }}
                                    </li>
                                </ul>
                            </td>
                            <td v-else>
                                {{ getDisplay(row[key]) }}
                            </td>
                        </template>
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
            'initManuscript',
            'initLocations',
            'initContents',
            'initPatrons',
            'initScribes'
        ],
        data() {
            return {
                manuscript: {
                    occurrencePatrons: [],
                    occurrenceScribes: []
                },
                locations: [],
                contents: [],
                patrons: [],
                scribes: [],
                model: {
                    city: null,
                    library: null,
                    collection: null,
                    shelf: null,
                    content: [],
                    patrons: [],
                    scribes: []
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
                contentSchema: {
                    fields: {
                        content: this.createMultiSelect('Content', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                patronsSchema: {
                    fields: {
                        patrons: this.createMultiSelect('Patrons', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                scribesSchema: {
                    fields: {
                        scribes: this.createMultiSelect('Scribes', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
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
                invalidForms: false,
                noNewValues: true
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.manuscript = JSON.parse(this.initManuscript)
                this.locations = JSON.parse(this.initLocations)
                this.contents = JSON.parse(this.initContents)
                this.patrons = JSON.parse(this.initPatrons)
                this.scribes = JSON.parse(this.initScribes)
            })
        },
        watch: {
            'manuscript': function (newValue, oldValue) {
                // Location
                this.model.city = this.manuscript.location.city
                this.model.library = this.manuscript.location.library
                this.model.collection = this.manuscript.location.collection
                this.model.shelf = this.manuscript.location.shelf

                // Content
                this.model.content = this.manuscript.content

                // People
                this.model.patrons = this.manuscript.patrons
                this.model.scribes = this.manuscript.scribes

                this.originalModel = Object.assign({}, this.model)

                if (this.locationSchema.fields.city.values.length === 0) {
                    this.loadLocationField(this.locationSchema.fields.city)
                }

                this.$refs.locationForm.validate()
                this.$refs.contentForm.validate()
                this.$refs.patronsForm.validate()
                this.$refs.scribesForm.validate()
            },
            'locations': function(newValue, oldValue)  {
                this.loadLocationField(this.locationSchema.fields.city)
                this.enableField(this.locationSchema.fields.city)
                this.loadLocationField(this.locationSchema.fields.library)
                this.loadLocationField(this.locationSchema.fields.collection)
            },
            'contents': function(newValue, oldValue) {
                this.contentSchema.fields.content.values = this.contents
                this.enableField(this.contentSchema.fields.content)
            },
            'patrons': function(newValue, oldValue) {
                this.patronsSchema.fields.patrons.values = this.patrons
                this.enableField(this.patronsSchema.fields.patrons)
            },
            'scribes': function(newValue, oldValue) {
                this.scribesSchema.fields.scribes.values = this.scribes
                this.enableField(this.scribesSchema.fields.scribes)
            },
            'model.city': function(newValue, oldValue) {
                if (newValue === undefined || newValue === null) {
                    this.dependencyField(this.locationSchema.fields.library)
                }
                else {
                    this.loadLocationField(this.locationSchema.fields.library)
                    this.enableField(this.locationSchema.fields.library)
                }
            },
            'model.library': function(newValue, oldValue) {
                if (newValue === undefined || newValue === null) {
                    this.dependencyField(this.locationSchema.fields.collection)
                }
                else {
                    this.loadLocationField(this.locationSchema.fields.collection)
                    this.enableField(this.locationSchema.fields.collection)
                }
            }
        },
        methods: {
            createMultiSelect(label, extra, extraSelectOptions) {
                let result = {
                    type: 'multiselectClear',
                    label: label,
                    placeholder: 'Loading',
                    model: label.toLowerCase(),
                    // Values will be loaded using a watcher
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
                if (extraSelectOptions !== undefined) {
                    for (let key of Object.keys(extraSelectOptions)) {
                        result['selectOptions'][key] = extraSelectOptions[key]
                    }
                }
                return result
            },
            loadLocationField(field) {
                let locations = Object.values(this.locations)
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
                field.values = values
            },
            validated(isValid, errors) {
                this.invalidForms = (
                    !this.$refs.hasOwnProperty('locationForm') || this.$refs.locationForm.errors.length > 0
                )
                this.noNewValues = (JSON.stringify(this.originalModel) === JSON.stringify(this.model))
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
            enableField(field) {
                if (field.values.length === 0) {
                    return this.noValuesField(field)
                }

                field.selectOptions.loading = false
                field.disabled = false
                let label = field.label.toLowerCase()
                let article = ['origin'].indexOf(label) < 0 ? 'a ' : 'an '
                field.placeholder = (field.selectOptions.multiple ? 'Select ' : 'Select ' + article) + label
            },
            calcDiff() {
                this.diff = []
                let fields = Object.assign(
                    {},
                    this.locationSchema.fields,
                    this.contentSchema.fields,
                    this.patronsSchema.fields,
                    this.scribesSchema.fields
                )
                for (let key of Object.keys(this.model)) {
                    if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])) {
                        this.diff.push({
                            'label': fields[key]['label'],
                            'old': this.originalModel[key],
                            'new': this.model[key],
                        })
                    }
                }
            },
            getDisplay(item) {
                if (
                    item === undefined
                    || item === null
                ) {
                    return null
                }
                else if (item.hasOwnProperty('name')) {
                    return item['name']
                }
                return item
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
                        // redirect to the detail page
                        window.location = this.getManuscriptUrl
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
