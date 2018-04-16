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
                        v-if="model.city"
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
                        v-if="model.city"
                        href="#"
                        class="action"
                        title="Add a new collection"
                        @click.prevent="editLibrary(true)">
                        <i class="fa fa-plus" />
                    </a>
                    <a
                        v-if="model.library"
                        href="#"
                        class="action"
                        title="Edit the selected library"
                        @click.prevent="editLibrary()">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        v-if="model.library && collectionSchema.fields.collection.values.length === 0"
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
                        v-if="model.library"
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
                v-if="submitModel.type === 'city'"
                :schema="editCitySchema"
                :model="submitModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <vue-form-generator
                v-if="submitModel.type === 'library'"
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
                    Edit {{ submitModel.type }}
                </h4>
                <h4
                    v-else
                    class="modal-title">
                    Add a new {{ submitModel.type }}
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
                <p>Are you sure you want to delete {{ submitModel.type }} "{{ submitModel[submitModel.type].name }}"?</p>
            </div>
            <div slot="header">
                <h4
                    v-if="submitModel[submitModel.type] != null"
                    class="modal-title">
                    Delete {{ submitModel.type }} {{ submitModel[submitModel.type].name }}
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
                    city: this.createMultiSelect('City', {}, {trackBy: 'id'}),
                }
            },
            librarySchema: {
                fields: {
                    library: this.createMultiSelect('Library', {dependency: 'city'}, {trackBy: 'id'}),
                }
            },
            collectionSchema: {
                fields: {
                    collection: this.createMultiSelect('Collection', {dependency: 'library'}, {trackBy: 'id'}),
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
                        model: 'city.individualName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editLibrarySchema: {
                fields: {
                    city: this.createMultiSelect('City', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Library name',
                        labelClasses: 'control-label',
                        model: 'library.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editCollectionSchema: {
                fields: {
                    city: this.createMultiSelect('City', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    library: this.createMultiSelect('Library', {required: true, validator: VueFormGenerator.validators.required, dependency: 'city'}, {trackBy: 'id'}),
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
                city: null,
                library: null,
                collection: null,
            },
            submitModel: {
                type: null,
                city: null,
                library: null,
                collection: null,
            },
            values: JSON.parse(this.initLocations),
        }
    },
    watch: {
        'model.city'() {
            if (this.model.city == null) {
                this.dependencyField(this.librarySchema.fields.library)
            }
            else {
                this.loadLocationField(this.librarySchema.fields.library)
                this.enableField(this.librarySchema.fields.library)
            }
        },
        'submitModel.city'() {
            if (this.submitModel.type === 'collection') {
                if (this.submitModel.city == null) {
                    this.dependencyField(this.editCollectionSchema.fields.library, this.submitModel)
                }
                else {
                    this.loadLocationField(this.editCollectionSchema.fields.library, this.submitModel)
                    this.enableField(this.editCollectionSchema.fields.library, this.submitModel)
                }
            }
        },
        'model.library'() {
            if (this.model.library == null) {
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
            this.submitModel.type = 'city'
            this.submitModel.city = this.model.city
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        editLibrary(add = false) {
            // TODO: check if name already exists
            this.submitModel.type = 'library'
            this.submitModel.city = this.model.city
            if (add) {
                this.submitModel.library =  {
                    id: null,
                    name: '',
                }
            }
            else {
                this.submitModel.library = this.model.library
            }

            this.loadLocationField(this.editLibrarySchema.fields.city, this.submitModel)
            this.enableField(this.editLibrarySchema.fields.city, this.submitModel)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delLibrary() {
            this.submitModel.type = 'library'
            this.submitModel.city = this.model.city
            this.submitModel.library = this.model.library
            this.deleteDependencies()
        },
        editCollection(add = false) {
            this.submitModel.type = 'collection'
            this.submitModel.city = this.model.city
            this.submitModel.library = this.model.library
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
            this.submitModel.city = this.model.city
            this.submitModel.library = this.model.library
            this.submitModel.collection = this.model.collection
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            let url = ''
            if (this.submitModel.type === 'library') {
                url = this.getManuscriptDepsByInstitutionUrl.replace('institution_id', this.submitModel.library.id)
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
            case 'city':
                // Not possible to add cities
                url = this.putRegionUrl.replace('region_id', this.submitModel.city.id)
                data = {
                    individualName: this.submitModel.city.individualName,
                }
                break
            case 'library':
                if (this.submitModel.library.id == null) {
                    url = this.postLibraryUrl
                    data = {
                        name: this.submitModel.library.name,
                        city: {
                            id: this.submitModel.city.id
                        }
                    }
                }
                else {
                    url = this.putLibraryUrl.replace('library_id', this.submitModel.library.id)
                    data = {}
                    if (this.submitModel.library.name !== this.originalSubmitModel.library.name) {
                        data.name = this.submitModel.library.name
                    }
                    if (this.submitModel.city.id !== this.originalSubmitModel.city.id) {
                        data.city = {
                            id: this.submitModel.city.id
                        }
                    }
                }
                break
            case 'collection':
                if (this.submitModel.collection.id == null) {
                    url = this.postCollectionUrl
                    data = {
                        name: this.submitModel.collection.name,
                        library: {
                            id: this.submitModel.library.id,
                        },
                    }
                }
                else {
                    url = this.putCollectionUrl.replace('collection_id', this.submitModel.collection.id)
                    data = {}
                    if (this.submitModel.collection.name !== this.originalSubmitModel.collection.name) {
                        data.name = this.submitModel.collection.name
                    }
                    if (this.submitModel.library.id !== this.originalSubmitModel.library.id) {
                        data.library = {
                            id: this.submitModel.library.id
                        }
                    }
                }
                break
            }
            if (this.submitModel[this.submitModel.type].id == null) {
                axios.post(url, data)
                    .then( (response) => {
                        switch(this.submitModel.type) {
                        case 'city':
                            this.submitModel.city = response.data
                            break
                        case 'library':
                            this.submitModel.library = response.data
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
                        this.alerts.push({type: 'error', message: 'Something whent wrong while adding a ' + this.submitModel.type + '.'})
                        console.log(error)
                    })
            }
            else {
                axios.put(url, data)
                    .then( (response) => {
                        switch(this.submitModel.type) {
                        case 'city':
                            this.submitModel.city = response.data
                            break
                        case 'library':
                            this.submitModel.library = response.data
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
                        this.alerts.push({type: 'error', message: 'Something whent wrong while updating the ' + this.submitModel.type + '.'})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.delModal = false
            this.openRequests++
            let url = ''
            switch(this.submitModel.type) {
            case 'library':
                url = this.deleteLibraryUrl.replace('library_id', this.submitModel.library.id)
                break
            case 'collection':
                url = this.deleteCollectionUrl.replace('collection_id', this.submitModel.collection.id)
                break
            }
            axios.delete(url)
                .then( (response) => {
                    switch(this.submitModel.type) {
                    case 'library':
                        this.submitModel.library = null
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
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the ' + this.submitModel.type + '.'})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.getLocationsUrl)
                .then( (response) => {
                    this.values = response.data
                    switch(this.submitModel.type) {
                    case 'city':
                        this.model.city = this.submitModel.city
                        this.loadLocationField(this.citySchema.fields.city, this.submitModel)
                        break
                    case 'library':
                        this.model.city = this.submitModel.city
                        this.model.library = this.submitModel.library
                        this.loadLocationField(this.citySchema.fields.city, this.submitModel)
                        this.loadLocationField(this.librarySchema.fields.library, this.submitModel)
                        this.enableField(this.librarySchema.fields.library, this.submitModel)
                        break
                    case 'collection':
                        this.model.city = this.submitModel.city
                        this.model.library = this.submitModel.library
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
    }
}
</script>
