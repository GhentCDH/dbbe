<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>

            <div class="panel panel-default">
                <div class="panel-heading">Edit region</div>
                <div class="panel-body">
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
                                :schema="monasterySchema"
                                :model="model"/>
                        </div>
                        <div class="col-xs-2 ptop-default">
                            <a
                                v-if="model.regionWithParents"
                                href="#"
                                class="action"
                                title="Add a new monastery"
                                @click.prevent="editMonastery(true)">
                                <i class="fa fa-plus" />
                            </a>
                            <a
                                v-if="model.institution"
                                href="#"
                                class="action"
                                title="Edit the selected monastery"
                                @click.prevent="editMonastery()">
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                v-if="model.institution"
                                href="#"
                                class="action"
                                title="Delete the selected monastery"
                                @click.prevent="delMonastery()">
                                <i class="fa fa-trash-o" />
                            </a>
                        </div>
                    </div>
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
                :schema="editMonasterySchema"
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
                <p>This origin has following dependencies that need to be resolved first:</p>
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
        initOrigins: {
            type: String,
            default: '',
        },
        getOriginsUrl: {
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
        getRegionsUrl: {
            type: String,
            default: '',
        },
        putRegionUrl: {
            type: String,
            default: '',
        },
        postMonasteryUrl: {
            type: String,
            default: '',
        },
        deleteMonasteryUrl: {
            type: String,
            default: '',
        },
        putMonasteryUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            alerts: [],
            citySchema: {
                fields: {
                    city: this.createMultiSelect('City', {model: 'regionWithParents'}, {customLabel: this.formatHistoricalName}),
                }
            },
            monasterySchema: {
                fields: {
                    monastery: this.createMultiSelect('Monastery', {model: 'institution', dependency: 'regionWithParents', dependencyName: 'city'}),
                }
            },
            delDependencies: [],
            delModal: false,
            editCitySchema: {
                fields: {
                    individualHistoricalName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'City name',
                        labelClasses: 'control-label',
                        model: 'regionWithParents.individualHistoricalName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editMonasterySchema: {
                fields: {
                    city: this.createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatHistoricalName}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Monastery name',
                        labelClasses: 'control-label',
                        model: 'institution.name',
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
            },
            submitModel: {
                type: null,
                regionWithParents: null,
                institution: null,
            },
            values: JSON.parse(this.initOrigins),
        }
    },
    watch: {
        'model.regionWithParents'() {
            if (this.model.regionWithParents == null) {
                this.dependencyField(this.monasterySchema.fields.monastery)
            }
            else {
                this.loadLocationField(this.monasterySchema.fields.monastery, this.model)
                this.enableField(this.monasterySchema.fields.monastery)
            }
        },
    },
    mounted () {
        this.loadLocationField(this.citySchema.fields.city)
        this.enableField(this.citySchema.fields.city)
        this.dependencyField(this.monasterySchema.fields.monastery)
    },
    methods: {
        editCity() {
            this.submitModel.type = 'regionWithParents'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        editMonastery(add = false) {
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

            this.loadLocationField(this.editMonasterySchema.fields.city, this.submitModel)
            this.enableField(this.editMonasterySchema.fields.city, this.submitModel)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delMonastery() {
            this.submitModel.type = 'institution'
            this.submitModel.regionWithParents = this.model.regionWithParents
            this.submitModel.institution = this.model.institution
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.get(this.getManuscriptDepsByInstitutionUrl.replace('institution_id', this.submitModel.institution.id))
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
                    individualHistoricalName: this.submitModel.regionWithParents.individualHistoricalName,
                }
                break
            case 'institution':
                if (this.submitModel.institution.id == null) {
                    url = this.postMonasteryUrl
                    data = {
                        name: this.submitModel.institution.name,
                        regionWithParents: {
                            id: this.submitModel.regionWithParents.id
                        }
                    }
                }
                else {
                    url = this.putMonasteryUrl.replace('monastery_id', this.submitModel.institution.id)
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
            axios.delete(this.deleteMonasteryUrl.replace('monastery_id', this.submitModel.institution.id))
                .then( (response) => {
                    this.submitModel.institution = null
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
            axios.get(this.getOriginsUrl)
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
                        this.loadLocationField(this.monasterySchema.fields.monastery, this.submitModel)
                        this.enableField(this.monasterySchema.fields.monastery, this.submitModel)
                        break
                    }
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while renewing the origin data.'})
                    console.log(error)
                })
        },
        formatType(type) {
            if (type === 'regionWithParents') {
                return 'city'
            }
            if (type === 'institution') {
                return 'monastery'
            }
            else {
                return type
            }
        },
        formatHistoricalName(regionWithParents) {
            return regionWithParents.historicalName
        },
    }
}
</script>
