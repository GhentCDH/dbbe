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
                                :schema="regionSchema"
                                :model="model" />
                        </div>
                        <div class="col-xs-2 ptop-default">
                            <a
                                href="#"
                                class="action"
                                title="Add a new region"
                                @click.prevent="editRegion(true)">
                                <i class="fa fa-plus" />
                            </a>
                            <a
                                v-if="model.region"
                                href="#"
                                class="action"
                                title="Edit the selected region"
                                @click.prevent="editRegion()">
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                v-if="model.region"
                                href="#"
                                class="action"
                                title="Merge the selected region with another region"
                                @click.prevent="mergeRegion()">
                                <i class="fa fa-compress" />
                            </a>
                            <a
                                v-if="model.region"
                                href="#"
                                class="action"
                                title="Delete the selected region"
                                @click.prevent="delRegion()">
                                <i class="fa fa-trash-o" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="model.region"
                class="panel panel-default">
                <div class="panel-heading">Additional region information</div>
                <div class="panel-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Historical name</td>
                                <td>{{ model.region.historicalName }}</td>
                            </tr>
                            <tr>
                                <td>Pleiades</td>
                                <td>{{ model.region.pleiades }}</td>
                            </tr>
                            <tr>
                                <td>Is city</td>
                                <td>{{ model.region.isCity ? 'Yes' : 'No' }}</td>
                            </tr>
                        </tbody>
                    </table>
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
                :schema="editRegionSchema"
                :model="editModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <div slot="header">
                <h4
                    v-if="editModel && editModel.id"
                    class="modal-title">
                    Edit region {{ editModal.name }}
                </h4>
                <h4
                    v-else
                    class="modal-title">
                    Add a new region
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    :disabled="JSON.stringify(editModel) === JSON.stringify(originalEditModel)"
                    type="warning"
                    @click="resetEdit()">
                    Reset
                </btn>
                <btn
                    v-if="editModel"
                    type="success"
                    :disabled="invalidEditForm || JSON.stringify(editModel) === JSON.stringify(originalEditModel)"
                    @click="submitEdit()">
                    {{ (editModel.id) ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="mergeModal"
            size="lg"
            auto-focus>
            <vue-form-generator
                :schema="mergeRegionSchema"
                :model="mergeModel"
                :options="formOptions"
                @validated="mergeFormValidated" />
            <div
                v-if="mergeModel.primary && mergeModel.secondary"
                class="panel panel-default">
                <div class="panel-heading">Preview of the merge</div>
                <div class="panel-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Name</td>
                                <td>{{ mergeModel.primary.name || mergeModel.secondary.name }}</td>
                            </tr>
                            <tr>
                                <td>Historical name</td>
                                <td>{{ mergeHistoricalName }}</td>
                            </tr>
                            <tr>
                                <td>Pleiades</td>
                                <td>{{ mergeModel.primary.pleiades || mergeModel.secondary.pleiades }}</td>
                            </tr>
                            <tr>
                                <td>Is city</td>
                                <td>{{ mergeModel.primary.isCity ? 'Yes' : 'No' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div slot="header">
                <h4 class="modal-title">
                    Merge regions
                </h4>
            </div>
            <div slot="footer">
                <btn @click="mergeModal=false">Cancel</btn>
                <btn
                    :disabled="JSON.stringify(mergeModel) === JSON.stringify(originalMergeModel)"
                    type="warning"
                    @click="resetMerge()">
                    Reset
                </btn>
                <btn
                    v-if="editModel"
                    type="success"
                    :disabled="invalidMergeForm"
                    @click="submitMerge()">
                    Merge
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            size="lg"
            auto-focus>
            <div v-if="Object.keys(delDependencies).length !== 0">
                <p>This region has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies.manuscripts"
                        :key="dependency.id">
                        Manuscript
                        <a :href="getManuscriptUrl.replace('manuscript_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                    <li
                        v-for="dependency in delDependencies.institutions"
                        :key="dependency.id">
                        Institution
                        <!-- <a :href="getInstitutionUrl.replace('institution_id', dependency.id)">{{ dependency.name }}</a> -->
                        {{ dependency.name }}
                    </li>
                    <li
                        v-for="dependency in delDependencies.regions"
                        :key="dependency.id">
                        Region
                        <!-- <a :href="getRegionUrl.replace('region_id', dependency.id)">{{ dependency.name }}</a> -->
                        {{ dependency.name }}
                    </li>
                </ul>
            </div>
            <div v-else-if="delModel">
                <p>Are you sure you want to delete region "{{ delModel.name }}"?</p>
            </div>
            <div slot="header">
                <h4
                    v-if="delModel"
                    class="modal-title">
                    Delete region {{ delModel.name }}
                </h4>
            </div>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    :disabled="Object.keys(delDependencies).length !== 0"
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
        initRegions: {
            type: String,
            default: '',
        },
        getRegionsUrl: {
            type: String,
            default: '',
        },
        getManuscriptDepsByRegionUrl: {
            type: String,
            default: '',
        },
        getManuscriptUrl: {
            type: String,
            default: '',
        },
        getInstitutionsByRegionUrl: {
            type: String,
            default: '',
        },
        getRegionsByRegionUrl: {
            type: String,
            default: '',
        },
        postRegionsUrl: {
            type: String,
            default: '',
        },
        putRegionUrl: {
            type: String,
            default: '',
        },
        putRegionMergeUrl: {
            type: String,
            default: '',
        },
        deleteRegionUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            alerts: [],
            delDependencies: {},
            delModal: false,
            delModel: {
                region: null,
            },
            editModal: false,
            editModel: {
                id: null,
                parent: null,
                name: null,
                historicalName: null,
                city: null,
                pleiades: null,
            },
            editRegionSchema: {
                fields: {
                    parent: this.createMultiSelect('Parent', null, {customLabel: this.formatNameHistoricalName}),
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Region name',
                        labelClasses: 'control-label',
                        model: 'individualName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    individualHistoricalName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Historical name',
                        labelClasses: 'control-label',
                        model: 'individualHistoricalName',
                        validator: VueFormGenerator.validators.string,
                    },
                    pleiades: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Pleiades Identifier',
                        labelClasses: 'control-label',
                        model: 'pleiades',
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'checkbox',
                        styleClasses: 'has-warning',
                        label: 'Is this region a city?',
                        labelClasses: 'control-label',
                        model: 'isCity',
                    },
                },
            },
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidEditForm: true,
            invalidMergeForm: true,
            mergeModal: false,
            mergeModel: {
                primary: null,
                secondary: null,
            },
            mergeRegionSchema: {
                fields: {
                    primary: this.createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatNameHistoricalName}),
                    secondary: this.createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatNameHistoricalName}),
                },
            },
            model: {
                region: null,
            },
            openRequests: 0,
            originalEditModel: {},
            originalMergeModel: {},
            regionSchema: {
                fields: {
                    region: this.createMultiSelect('Region', null, {customLabel: this.formatNameHistoricalName}),
                },
            },
            regionValues: JSON.parse(this.initRegions),
        }
    },
    computed: {
        mergeHistoricalName: function () {
            if (
                (this.mergeModel.primary.individualHistoricalName != null && this.mergeModel.primary.individualHistoricalName !== '')
                || (this.mergeModel.secondary.individualHistoricalName == null || this.mergeModel.secondary.individualHistoricalName === '')
            ) {
                return this.mergeModel.primary.historicalName
            }
            else {
                let historicalParent = this.mergeModel.primary.historicalName.substring(0, this.mergeModel.primary.historicalName.lastIndexOf('>'))
                if (historicalParent !== '') {
                    return this.mergeModel.primary.historicalName.substring(0, this.mergeModel.primary.historicalName.lastIndexOf('>')) + '> ' + this.mergeModel.secondary.individualHistoricalName
                }
                else {
                    return this.mergeModel.secondary.individualHistoricalName
                }
            }
        }
    },
    watch: {
        'model.region'() {
            // set full parent, so the name can be formatted correctly
            if (this.model.region != null && this.model.region.parent != null) {
                this.model.region.parent = this.regionValues.filter((regionWithParents) => regionWithParents.id === this.model.region.parent.id)[0]
            }
        },
    },
    mounted () {
        this.regionSchema.fields.region.values = this.regionValues
        this.enableField(this.regionSchema.fields.region)
    },
    methods: {
        editRegion(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.editModel =  {
                    id: null,
                    name: null,
                    parent: this.model.region,
                    individualName: null,
                    individualHistoricalName: null,
                    pleiades: null,
                    isCity: null,
                }
            }
            else {
                this.editModel = this.model.region
            }
            this.editRegionSchema.fields.parent.values = this.regionValues
                .filter(region => region.id != this.editModel.id) // Remove current region
            this.enableField(this.editRegionSchema.fields.parent)
            this.originalEditModel = JSON.parse(JSON.stringify(this.editModel))
            this.editModal = true
        },
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
        resetEdit() {
            this.editModel = JSON.parse(JSON.stringify(this.originalEditModel))
        },
        mergeRegion() {
            this.mergeModel.primary = this.model.region
            this.mergeModel.secondary = null
            this.mergeRegionSchema.fields.primary.values = this.regionValues
            this.mergeRegionSchema.fields.secondary.values = this.regionValues
            this.enableField(this.mergeRegionSchema.fields.primary)
            this.enableField(this.mergeRegionSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        mergeFormValidated(isValid, errors) {
            this.invalidMergeForm = !isValid
        },
        resetMerge() {
            this.mergeModel = JSON.parse(JSON.stringify(this.originalMergeModel))
        },
        delRegion() {
            this.delModel = this.model.region
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.all([
                axios.get(this.getManuscriptDepsByRegionUrl.replace('region_id', this.delModel.id)),
                axios.get(this.getInstitutionsByRegionUrl.replace('region_id', this.delModel.id)),
                axios.get(this.getRegionsByRegionUrl.replace('region_id', this.delModel.id)),
            ])
                .then((results) => {
                    this.delDependencies = {}
                    if (results[0].data.length > 0) {
                        this.delDependencies.manuscripts = results[0].data
                    }
                    if (results[1].data.length > 0) {
                        this.delDependencies.institutions = results[1].data
                    }
                    if (results[2].data.length > 0) {
                        this.delDependencies.regions = results[2].data
                    }
                    this.delModal = true
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while checking for dependencies.'})
                    console.log(error)
                })
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.editModel.id == null) {
                axios.post(this.postRegionsUrl, {
                    parent: this.editModel.parent == null ? null : {
                        id: this.editModel.parent.id,
                    },
                    individualName: this.editModel.individualName,
                    individualHistoricalName: this.editModel.individualHistoricalName,
                    pleiades: this.editModel.pleiades,
                    isCity: this.editModel.isCity,
                })
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while adding the region.'})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.editModel.parent) !== JSON.stringify(this.originalEditModel.parent)) {
                    if (this.editModel.parent == null) {
                        data.parent = null
                    }
                    else {
                        data.parent = {
                            id: this.editModel.parent.id
                        }
                    }
                }
                if (this.editModel.individualName !== this.originalEditModel.individualName) {
                    data.individualName = this.editModel.individualName
                }
                if (this.editModel.individualHistoricalName !== this.originalEditModel.individualHistoricalName) {
                    data.individualHistoricalName = this.editModel.individualHistoricalName
                }
                if (this.editModel.pleiades !== this.originalEditModel.pleiades) {
                    data.pleiades = this.editModel.pleiades
                }
                if (this.editModel.isCity !== this.originalEditModel.isCity) {
                    data.isCity = this.editModel.isCity
                }
                axios.put(this.putRegionUrl.replace('region_id', this.editModel.id), data)
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while updating the region.'})
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.putRegionMergeUrl.replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.editModel = response.data
                    this.update()
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the region.'})
                    console.log(error)
                })
        },
        submitDelete() {
            this.delModal = false
            this.openRequests++
            axios.delete(this.deleteRegionUrl.replace('region_id', this.delModel.id))
                .then( (response) => {
                    this.delModel = null
                    this.editModel = null
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.update()
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the region.'})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.getRegionsUrl)
                .then( (response) => {
                    this.regionValues = response.data
                    this.regionSchema.fields.region.values = this.regionValues
                    this.model.region = this.editModel
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while renewing the region data.'})
                    console.log(error)
                })
        },
        formatNameHistoricalName(regionWithParents) {
            return regionWithParents.name !== '' ? regionWithParents.name : '[' + regionWithParents.historicalName + ']'
        },
    }
}
</script>
