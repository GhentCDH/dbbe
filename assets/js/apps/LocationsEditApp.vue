<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel
                header="Edit Locations"
                :links="[{
                    url: urls['regions_edit'],
                    text: 'Add, edit or delete cities (regions)',
                }]"
            >
                <editListRow
                    :schema="citySchema"
                    :model="model"
                    name="city"
                    :conditions="{
                        edit: model.regionWithParents,
                    }"
                    :titles="{edit: 'Edit the name of the selected city'}"
                    @edit="editCity()" />
                <editListRow
                    :schema="librarySchema"
                    :model="model"
                    name="library"
                    :conditions="{
                        add: model.regionWithParents,
                        edit: model.institution,
                        del: model.institution && collectionSchema.fields.collection.values.length === 0,
                    }"
                    @add="editLibrary(true)"
                    @edit="editLibrary()"
                    @del="delLibrary()" />
                <editListRow
                    :schema="collectionSchema"
                    :model="model"
                    name="collection"
                    :conditions="{
                        add: model.institution,
                        edit: model.collection,
                        del: model.collection,
                    }"
                    @add="editCollection(true)"
                    @edit="editCollection()"
                    @del="delCollection()" />
            </panel>

            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :format-type="formatType"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            :format-type="formatType"
            :alerts="deleteAlerts"
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)" />
    </div>
</template>

<script>
import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'

import AbstractListEdit from '../mixins/AbstractListEdit'
import {
  createMultiSelect, dependencyField,
  enableField, loadLocationField
} from '@/helpers/formFieldUtils';
export default {
    mixins: [
        AbstractListEdit,
    ],
    data() {
        return {
            citySchema: {
                fields: {
                    city: createMultiSelect('City', {model: 'regionWithParents'}),
                }
            },
            librarySchema: {
                fields: {
                    library: createMultiSelect('Library', {model: 'institution', dependency: 'regionWithParents', dependencyName: 'city'}),
                }
            },
            collectionSchema: {
                fields: {
                    collection: createMultiSelect('Collection', {model: 'collection', dependency: 'institution', dependencyName: 'library'}),
                }
            },
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
                    city: createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
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
                    city: createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
                    library: createMultiSelect('Library', {model: 'institution', required: true, validator: VueFormGenerator.validators.required, dependency: 'regionWithParents', dependencyName: 'city'}),
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
            model: {
                regionWithParents: null,
                institution: null,
                collection: null,
            },
            submitModel: {
                submitType: null,
                regionWithParents: null,
                institution: null,
                collection: null,
            },
        }
    },
    computed: {
        editSchema: function() {
            switch (this.submitModel.submitType) {
            case 'regionWithParents':
                return this.editCitySchema
                break;
            case 'institution':
                return this.editLibrarySchema
                break;
            default:
                return this.editCollectionSchema
            }
        },
        depUrls: function() {
            let depUrls = {
                'Manuscripts': {
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                }
            }
            if (this.submitModel.submitType === 'institution') {
                depUrls['Manuscripts']['depUrl'] = this.urls['manuscript_deps_by_institution'].replace('institution_id', this.submitModel.institution.id)
                depUrls['Manuscripts'] = {
                    url: this.urls['online_source_get'],
                    urlIdentifier: 'online_source_id',
                    depUrl: this.urls['online_source_deps_by_institution'].replace('institution_id', this.submitModel.institution.id)
                }
            }
            else {
                depUrls['Manuscripts']['depUrl'] = this.urls['manuscript_deps_by_collection'].replace('collection_id', this.submitModel.collection.id)
            }
            return depUrls
        },
    },
    watch: {
        'model.regionWithParents'() {
            if (this.model.regionWithParents == null) {
                dependencyField(this.librarySchema.fields.library, this.model)
            }
            else {
                loadLocationField(this.librarySchema.fields.library, this.model, this.values)
                enableField(this.librarySchema.fields.library, this.model)
            }
        },
        'submitModel.regionWithParents'() {
            if (this.submitModel.submitType === 'collection') {
                if (this.submitModel.regionWithParents == null) {
                    dependencyField(this.editCollectionSchema.fields.library, this.model)
                }
                else {
                    loadLocationField(this.editCollectionSchema.fields.library, this.model, this.values)
                    enableField(this.editCollectionSchema.fields.library, this.model)
                }
            }
        },
        'model.institution'() {
            if (this.model.institution == null) {
                dependencyField(this.collectionSchema.fields.collection, this.model)
            }
            else {
                loadLocationField(this.collectionSchema.fields.collection, this.model, this.values)
                enableField(this.collectionSchema.fields.collection, this.model)
            }
        },
    },
    mounted () {
        loadLocationField(this.citySchema.fields.city, this.model, this.values)
        enableField(this.citySchema.fields.city, this.model)
        dependencyField(this.librarySchema.fields.library, this.model)
        dependencyField(this.collectionSchema.fields.collection, this.model)
    },
    methods: {
        editCity() {
            this.submitModel.submitType = 'regionWithParents'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        editLibrary(add = false) {
            // TODO: check if name already exists
            this.submitModel.submitType = 'institution'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            if (add) {
                this.submitModel.institution =  {
                    id: null,
                    name: '',
                }
            }
            else {
                this.submitModel.institution = JSON.parse(JSON.stringify(this.model.institution))
            }

            loadLocationField(this.editLibrarySchema.fields.city, this.model, this.values)
            enableField(this.editLibrarySchema.fields.city, this.model)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delLibrary() {
            this.submitModel.submitType = 'institution'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.submitModel.institution = this.model.institution
            this.deleteDependencies()
        },
        editCollection(add = false) {
            this.submitModel.submitType = 'collection'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.submitModel.institution = JSON.parse(JSON.stringify(this.model.institution))
            if (add) {
                this.submitModel.collection = {
                    id: null,
                    name: '',
                }
            }
            else {
                this.submitModel.collection = JSON.parse(JSON.stringify(this.model.collection))
            }

            loadLocationField(this.editCollectionSchema.fields.city, this.model,this.values)
            loadLocationField(this.editCollectionSchema.fields.library, this.model,this.values)
            enableField(this.editCollectionSchema.fields.city, this.model)
            enableField(this.editCollectionSchema.fields.library, this.model)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delCollection() {
            this.submitModel.submitType = 'collection'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.submitModel.institution = JSON.parse(JSON.stringify(this.model.institution))
            this.submitModel.collection = JSON.parse(JSON.stringify(this.model.collection))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            let url = ''
            let data = {}
            switch(this.submitModel.submitType) {
            case 'regionWithParents':
                // Not possible to add cities
                url = this.urls['region_put'].replace('region_id', this.submitModel.regionWithParents.id)
                data = {
                    individualName: this.submitModel.regionWithParents.individualName,
                }
                break
            case 'institution':
                if (this.submitModel.institution.id == null) {
                    url = this.urls['library_post']
                    data = {
                        name: this.submitModel.institution.name,
                        regionWithParents: {
                            id: this.submitModel.regionWithParents.id
                        }
                    }
                }
                else {
                    url = this.urls['library_put'].replace('library_id', this.submitModel.institution.id)
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
                    url = this.urls['collection_post']
                    data = {
                        name: this.submitModel.collection.name,
                        institution: {
                            id: this.submitModel.institution.id,
                        },
                    }
                }
                else {
                    url = this.urls['collection_put'].replace('collection_id', this.submitModel.collection.id)
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
            if (this.submitModel[this.submitModel.submitType].id == null) {
                axios.post(url, data)
                    .then( (response) => {
                        switch(this.submitModel.submitType) {
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
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding a ' + this.formatType(this.submitModel.submitType) + '.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(url, data)
                    .then( (response) => {
                        switch(this.submitModel.submitType) {
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
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the ' + this.formatType(this.submitModel.submitType) + '.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            let url = ''
            switch(this.submitModel.submitType) {
            case 'institution':
                url = this.urls['library_delete'].replace('library_id', this.submitModel.institution.id)
                break
            case 'collection':
                url = this.urls['collection_delete'].replace('collection_id', this.submitModel.collection.id)
                break
            }
            axios.delete(url)
                .then( (response) => {
                    switch(this.submitModel.submitType) {
                    case 'institution':
                        this.submitModel.institution = null
                        break
                    case 'collection':
                        this.submitModel.collection = null
                        break
                    }
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the ' + this.formatType(this.submitModel.submitType) + '.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['locations_get'])
                .then( (response) => {

                    this.values = response.data
                    switch(this.submitModel.submitType) {
                    case 'regionWithParents':
                        this.model.regionWithParents = JSON.parse(JSON.stringify(this.submitModel.regionWithParents))
                        loadLocationField(this.citySchema.fields.city, this.model, this.values)
                        break
                    case 'institution':
                        this.model.regionWithParents = JSON.parse(JSON.stringify(this.submitModel.regionWithParents))
                        this.model.institution = JSON.parse(JSON.stringify(this.submitModel.institution))
                        loadLocationField(this.citySchema.fields.city, this.model,this.values)
                        loadLocationField(this.librarySchema.fields.library, this.model,this.values)
                        enableField(this.librarySchema.fields.library, this.model)
                        break
                    case 'collection':
                        this.model.regionWithParents = JSON.parse(JSON.stringify(this.submitModel.regionWithParents))
                        this.model.institution = JSON.parse(JSON.stringify(this.submitModel.institution))
                        this.model.collection = JSON.parse(JSON.stringify(this.submitModel.collection))
                        loadLocationField(this.citySchema.fields.city, this.model,this.values)
                        loadLocationField(this.librarySchema.fields.library, this.model,this.values)
                        loadLocationField(this.collectionSchema.fields.collection, this.model,this.values)
                        enableField(this.collectionSchema.fields.collection, this.model)
                        break
                    }
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the location data.', login: this.isLoginError(error)})
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
