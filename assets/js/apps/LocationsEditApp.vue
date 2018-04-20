<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <h2>
                Edit Locations
            </h2>
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>

            <div class="row">
                <div class="col-xs-10">
                    <vue-form-generator
                        :schema="citySchema"
                        :model="model" />
                    <p><span class="small">Cities can be added and deleted on the <a :href="getRegionsUrl">edit regions page</a>.</span></p>
                </div>
                <div class="col-xs-2 ptop-default">
                    <a
                        v-if="model.regionWithParents"
                        href="#"
                        class="action"
                        title="Edit the selected city"
                        @click.prevent="editCity()">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-10">
                    <vue-form-generator
                        :schema="librarySchema"
                        :model="model"/>
                </div>
                <div class="col-xs-2 ptop-default">
                    <a
                        v-if="model.regionWithParents"
                        href="#"
                        class="action"
                        title="Add a new library"
                        @click.prevent="editLibrary(true)">
                        <i class="fa fa-plus" />
                    </a>
                    <a
                        v-if="model.institution"
                        href="#"
                        class="action"
                        title="Edit the selected library"
                        @click.prevent="editLibrary()">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        v-if="model.institution && collectionSchema.fields.collection.values.length === 0"
                        href="#"
                        class="action"
                        title="Delete the selected library"
                        @click.prevent="delLibrary()">
                        <i class="fa fa-trash-o" />
                    </a>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-10">
                    <vue-form-generator
                        :schema="collectionSchema"
                        :model="model"/>
                </div>
                <div class="col-xs-2 ptop-default">
                    <a
                        v-if="model.institution"
                        href="#"
                        class="action"
                        title="Add a new collection"
                        @click.prevent="editCollection(true)">
                        <i class="fa fa-plus" />
                    </a>
                    <a
                        v-if="model.collection"
                        href="#"
                        class="action"
                        title="Edit the selected collection"
                        @click.prevent="editCollection()">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        v-if="model.collection"
                        href="#"
                        class="action"
                        title="Delete the selected collection"
                        @click.prevent="delCollection()">
                        <i class="fa fa-trash-o" />
                    </a>
                </div>
            </div>

            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <modal
            v-model="editModal"
            size="lg"
            auto-focus>
            <vue-form-generator
                v-if="submitModel.type === 'regionWithParents'"
                :schema="editCitySchema"
                :model="submitModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <vue-form-generator
                v-if="submitModel.type === 'institution'"
                :schema="editLibrarySchema"
                :model="submitModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <vue-form-generator
                v-if="submitModel.type === 'collection'"
                :schema="editCollectionSchema"
                :model="submitModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <div slot="header">
                <h4
                    v-if="submitModel[submitModel.type] && submitModel[submitModel.type].id"
                    class="modal-title">
                    Edit {{ formatType(submitModel.type) }}
                </h4>
                <h4
                    v-else
                    class="modal-title">
                    Add a new {{ formatType(submitModel.type) }}
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    :disabled="JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                    type="warning"
                    @click="reset()">
                    Reset
                </btn>
                <btn
                    type="success"
                    :disabled="invalidEditForm || JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                    @click="submit()">
                    {{ submitModel[submitModel.type] && submitModel[submitModel.type].id ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            size="lg"
            auto-focus>
            <div v-if="delDependencies.length !== 0">
                <p>This location has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies"
                        :key="dependency.id">
                        <a :href="getManuscriptUrl.replace('manuscript_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                </ul>
            </div>
            <div v-else-if="submitModel[submitModel.type] != null">
                <p>Are you sure you want to delete {{ formatType(submitModel.type) }} "{{ submitModel[submitModel.type].name }}"?</p>
            </div>
            <div slot="header">
                <h4
                    v-if="submitModel[submitModel.type] != null"
                    class="modal-title">
                    Delete {{ formatType(submitModel.type) }} {{ submitModel[submitModel.type].name }}
                </h4>
            </div>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    :disabled="delDependencies.length !== 0"
                    @click="submitDelete()">
                    Delete
                </btn>
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

import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear'

import Fields from '../Components/Fields'

Vue.use(VueFormGenerator)
Vue.use(uiv)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)

export default {
    mixins: [ Fields ],
    props: {
        initLocations: {
            type: String,
            default: '',
        },
        getLocationsUrl: {
            type: String,
            default: '',
        },
        getManuscriptUrl: {
            type: String,
            default: '',
        },
        getManuscriptDepsByInstitutionUrl: {
            type: String,
            default: '',
        },
        getManuscriptDepsByCollectionUrl: {
            type: String,
            default: '',
        },
        getRegionsUrl: {
            type: String,
            default: '',
        },
        putRegionUrl: {
            type: String,
            default: '',
        },
        postLibraryUrl: {
            type: String,
            default: '',
        },
        deleteLibraryUrl: {
            type: String,
            default: '',
        },
        putLibraryUrl: {
            type: String,
            default: '',
        },
        postCollectionUrl: {
            type: String,
            default: '',
        },
        putCollectionUrl: {
            type: String,
            default: '',
        },
        deleteCollectionUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            alerts: [],
            citySchema: {
                fields: {
                    city: this.createMultiSelect('City', {model: 'regionWithParents'}),
                }
            },
            librarySchema: {
                fields: {
                    library: this.createMultiSelect('Library', {model: 'institution', dependency: 'regionWithParents', dependencyName: 'city'}),
                }
            },
            collectionSchema: {
                fields: {
                    collection: this.createMultiSelect('Collection', {model: 'collection', dependency: 'institution', dependencyName: 'library'}),
                }
            },
            delDependencies: [],
            delModal: false,
            editCitySchema: {
                fields: {
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City name',
                        labelClasses: 'control-label',
                        model: 'regionWithParents.individualName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editLibrarySchema: {
                fields: {
                    city: this.createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Library name',
                        labelClasses: 'control-label',
                        model: 'institution.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editCollectionSchema: {
                fields: {
                    city: this.createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
                    library: this.createMultiSelect('Library', {model: 'institution', required: true, validator: VueFormGenerator.validators.required, dependency: 'regionWithParents', dependencyName: 'city'}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Collection name',
                        labelClasses: 'control-label',
                        model: 'collection.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editModal: false,
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidEditForm: true,
            openRequests: 0,
            originalSubmitModel: {},
            model: {
                regionWithParents: null,
                institution: null,
                collection: null,
            },
            submitModel: {
                type: null,
                regionWithParents: null,
                institution: null,
                collection: null,
            },
            values: JSON.parse(this.initLocations),
        }
    },
    watch: {
        'model.regionWithParents'() {
            if (this.model.regionWithParents == null) {
                this.dependencyField(this.librarySchema.fields.library)
            }
            else {
                this.loadLocationField(this.librarySchema.fields.library, this.model)
                this.enableField(this.librarySchema.fields.library)
            }
        },
        'submitModel.regionWithParents'() {
            if (this.submitModel.type === 'collection') {
                if (this.submitModel.regionWithParents == null) {
                    this.dependencyField(this.editCollectionSchema.fields.library, this.submitModel)
                }
                else {
                    this.loadLocationField(this.editCollectionSchema.fields.library, this.submitModel)
                    this.enableField(this.editCollectionSchema.fields.library, this.submitModel)
                }
            }
        },
        'model.institution'() {
            if (this.model.institution == null) {
                this.dependencyField(this.collectionSchema.fields.collection)
            }
            else {
                this.loadLocationField(this.collectionSchema.fields.collection)
                this.enableField(this.collectionSchema.fields.collection)
            }
        },
    },
    mounted () {
        this.loadLocationField(this.citySchema.fields.city)
        this.enableField(this.citySchema.fields.city)
        this.dependencyField(this.librarySchema.fields.library)
        this.dependencyField(this.collectionSchema.fields.collection)
    },
    methods: {
        editCity() {
            this.submitModel.type = 'regionWithParents'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        editLibrary(add = false) {
            // TODO: check if name already exists
            this.submitModel.type = 'institution'
            this.submitModel.regionWithParents = this.model.regionWithParents
            if (add) {
                this.submitModel.institution =  {
                    id: null,
                    name: '',
                }
            }
            else {
                this.submitModel.institution = this.model.institution
            }

            this.loadLocationField(this.editLibrarySchema.fields.city, this.submitModel)
            this.enableField(this.editLibrarySchema.fields.city, this.submitModel)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delLibrary() {
            this.submitModel.type = 'institution'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.submitModel.institution = this.model.institution
            this.deleteDependencies()
        },
        editCollection(add = false) {
            this.submitModel.type = 'collection'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.submitModel.institution = this.model.institution
            if (add) {
                this.submitModel.collection = {
                    id: null,
                    name: '',
                }
            }
            else {
                this.submitModel.collection = this.model.collection
            }

            this.loadLocationField(this.editCollectionSchema.fields.city, this.submitModel)
            this.loadLocationField(this.editCollectionSchema.fields.library, this.submitModel)
            this.enableField(this.editCollectionSchema.fields.city, this.submitModel)
            this.enableField(this.editCollectionSchema.fields.library, this.submitModel)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delCollection() {
            this.submitModel.type = 'collection'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.submitModel.institution = this.model.institution
            this.submitModel.collection = this.model.collection
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            let url = ''
            if (this.submitModel.type === 'institution') {
                url = this.getManuscriptDepsByInstitutionUrl.replace('institution_id', this.submitModel.institution.id)
            }
            else {
                url = this.getManuscriptDepsByCollectionUrl.replace('collection_id', this.submitModel.collection.id)
            }
            axios.get(url)
                .then( (response) => {
                    this.delDependencies = response.data
                    this.delModal = true
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while checking for dependencies.'})
                    console.log(error)
                })
        },
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
        reset() {
            this.submitModel = JSON.parse(JSON.stringify(this.originalSubmitModel))
        },
        submit() {
            this.editModal = false
            this.openRequests++
            let url = ''
            let data = {}
            switch(this.submitModel.type) {
            case 'regionWithParents':
                // Not possible to add cities
                url = this.putRegionUrl.replace('region_id', this.submitModel.regionWithParents.id)
                data = {
                    individualName: this.submitModel.regionWithParents.individualName,
                }
                break
            case 'institution':
                if (this.submitModel.institution.id == null) {
                    url = this.postLibraryUrl
                    data = {
                        name: this.submitModel.institution.name,
                        regionWithParents: {
                            id: this.submitModel.regionWithParents.id
                        }
                    }
                }
                else {
                    url = this.putLibraryUrl.replace('library_id', this.submitModel.institution.id)
                    data = {}
                    if (this.submitModel.institution.name !== this.originalSubmitModel.institution.name) {
                        data.name = this.submitModel.institution.name
                    }
                    if (this.submitModel.regionWithParents.id !== this.originalSubmitModel.regionWithParents.id) {
                        data.regionWithParents = {
                            id: this.submitModel.regionWithParents.id
                        }
                    }
                }
                break
            case 'collection':
                if (this.submitModel.collection.id == null) {
                    url = this.postCollectionUrl
                    data = {
                        name: this.submitModel.collection.name,
                        institution: {
                            id: this.submitModel.institution.id,
                        },
                    }
                }
                else {
                    url = this.putCollectionUrl.replace('collection_id', this.submitModel.collection.id)
                    data = {}
                    if (this.submitModel.collection.name !== this.originalSubmitModel.collection.name) {
                        data.name = this.submitModel.collection.name
                    }
                    if (this.submitModel.institution.id !== this.originalSubmitModel.institution.id) {
                        data.institution = {
                            id: this.submitModel.institution.id
                        }
                    }
                }
                break
            }
            if (this.submitModel[this.submitModel.type].id == null) {
                axios.post(url, data)
                    .then( (response) => {
                        switch(this.submitModel.type) {
                        case 'regionWithParents':
                            this.submitModel.regionWithParents = response.data
                            break
                        case 'institution':
                            this.submitModel.institution = response.data
                            break
                        case 'collection':
                            this.submitModel.collection = response.data
                            break
                        }
                        this.update()
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while adding a ' + this.formatType(this.submitModel.type) + '.'})
                        console.log(error)
                    })
            }
            else {
                axios.put(url, data)
                    .then( (response) => {
                        switch(this.submitModel.type) {
                        case 'regionWithParents':
                            this.submitModel.regionWithParents = response.data
                            break
                        case 'institution':
                            this.submitModel.institution = response.data
                            break
                        case 'collection':
                            this.submitModel.collection = response.data
                            break
                        }
                        this.update()
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while updating the ' + this.formatType(this.submitModel.type) + '.'})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.delModal = false
            this.openRequests++
            let url = ''
            switch(this.submitModel.type) {
            case 'institution':
                url = this.deleteLibraryUrl.replace('library_id', this.submitModel.institution.id)
                break
            case 'collection':
                url = this.deleteCollectionUrl.replace('collection_id', this.submitModel.collection.id)
                break
            }
            axios.delete(url)
                .then( (response) => {
                    switch(this.submitModel.type) {
                    case 'institution':
                        this.submitModel.institution = null
                        break
                    case 'collection':
                        this.submitModel.collection = null
                        break
                    }
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.update()
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the ' + this.formatType(this.submitModel.type) + '.'})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.getLocationsUrl)
                .then( (response) => {
                    this.values = response.data
                    switch(this.submitModel.type) {
                    case 'regionWithParents':
                        this.model.regionWithParents = this.submitModel.regionWithParents
                        this.loadLocationField(this.citySchema.fields.city, this.submitModel)
                        break
                    case 'institution':
                        this.model.regionWithParents = this.submitModel.regionWithParents
                        this.model.institution = this.submitModel.institution
                        this.loadLocationField(this.citySchema.fields.city, this.submitModel)
                        this.loadLocationField(this.librarySchema.fields.library, this.submitModel)
                        this.enableField(this.librarySchema.fields.library, this.submitModel)
                        break
                    case 'collection':
                        this.model.regionWithParents = this.submitModel.regionWithParents
                        this.model.institution = this.submitModel.institution
                        this.model.collection = this.submitModel.collection
                        this.loadLocationField(this.citySchema.fields.city, this.submitModel)
                        this.loadLocationField(this.librarySchema.fields.library, this.submitModel)
                        this.loadLocationField(this.collectionSchema.fields.collection, this.submitModel)
                        this.enableField(this.collectionSchema.fields.collection, this.submitModel)
                        break
                    }
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while renewing the location data.'})
                    console.log(error)
                })
        },
        formatType(type) {
            if (type === 'regionWithParents') {
                return 'city'
            }
            if (type === 'institution') {
                return 'library'
            }
            else {
                return type
            }
        },
    }
}
</script>
