<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit regions">
                <editListRow
                    :schema="regionSchema"
                    :model="model"
                    name="region"
                    :conditions="{
                        add: true,
                        edit: model.region,
                        merge: model.region,
                        del: model.region,
                    }"
                    @add="editRegion(true)"
                    @edit="editRegion()"
                    @merge="mergeRegion()"
                    @del="delRegion()" />
            </panel>
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
        <editModal
            :show="editModal"
            :schema="editRegionSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
        <mergeModal
            :show="mergeModal"
            :schema="mergeRegionSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge()"
            @confirm="submitMerge()"
            @dismiss-alert="mergeAlerts.splice($event, 1)">
            <table
                v-if="mergeModel.primary && mergeModel.secondary"
                slot="preview"
                class="table table-striped table-hover">
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
        </mergeModal>
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            :alerts="deleteAlerts"
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)" />
    </div>
</template>

<script>
import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'

import AbstractListEdit from '../Components/Edit/AbstractListEdit'
import {createMultiSelect, enableField} from "@/Components/FormFields/formFieldUtils";

export default {
    mixins: [
        AbstractListEdit,
    ],
    data() {
        return {
            regionSchema: {
                fields: {
                    region: createMultiSelect('Region', null, {customLabel: this.formatNameHistoricalName}),
                },
            },
            editRegionSchema: {
                fields: {
                    parent: createMultiSelect('Parent', {model: 'region.parent'}, {customLabel: this.formatNameHistoricalName}),
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Region name',
                        labelClasses: 'control-label',
                        model: 'region.individualName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    individualHistoricalName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Historical name',
                        labelClasses: 'control-label',
                        model: 'region.individualHistoricalName',
                        validator: VueFormGenerator.validators.string,
                    },
                    pleiades: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Pleiades Identifier',
                        labelClasses: 'control-label',
                        model: 'region.pleiades',
                        validator: VueFormGenerator.validators.number,
                    },
                    city: {
                        type: 'checkbox',
                        styleClasses: 'has-warning',
                        label: 'Is this region a city?',
                        labelClasses: 'control-label',
                        model: 'region.isCity',
                    },
                },
            },
            mergeRegionSchema: {
                fields: {
                    primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatNameHistoricalName}),
                    secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatNameHistoricalName}),
                },
            },
            model: {
                region: null,
            },
            submitModel: {
                submitType: 'region',
                region: {
                    id: null,
                    parent: null,
                    name: null,
                    historicalName: null,
                    city: null,
                    pleiades: null,
                }
            },
            mergeModel: {
                submitType: 'regions',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function () {
            return {
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_region'].replace('region_id', this.submitModel.region.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
                'Institutions': {
                    depUrl: this.urls['institution_deps_by_region'].replace('region_id', this.submitModel.region.id),
                },
                'Offices': {
                    depUrl: this.urls['office_deps_by_region'].replace('region_id', this.submitModel.region.id),
                },
                'Persons': {
                    depUrl: this.urls['person_deps_by_region'].replace('region_id', this.submitModel.region.id),
                    url: this.urls['person_get'],
                    urlIdentifier: 'person_id',
                },
                'Regions': {
                    depUrl: this.urls['region_deps_by_region'].replace('region_id', this.submitModel.region.id),
                },
            }
        },
        mergeHistoricalName: function () {
            if (
                (this.mergeModel.primary.individualHistoricalName != null && this.mergeModel.primary.individualHistoricalName !== '')
                || (this.mergeModel.secondary == null || this.mergeModel.secondary.individualHistoricalName == null || this.mergeModel.secondary.individualHistoricalName === '')
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
        },
    },
    watch: {
        'model.region'() {
            // set full parent, so the name can be formatted correctly
            if (this.model.region != null && this.model.region.parent != null) {
                this.model.region.parent = this.values.filter((regionWithParents) => regionWithParents.id === this.model.region.parent.id)[0]
            }
        },
    },
    mounted () {
        this.regionSchema.fields.region.values = this.values
        enableField(this.regionSchema.fields.region)
    },
    methods: {
        editRegion(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.region =  {
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
                this.submitModel.region = JSON.parse(JSON.stringify(this.model.region))
            }
            this.editRegionSchema.fields.parent.values = this.values
                .filter((region) => !this.isOrIsChild(region, this.model.region)) // Remove values that create cycles
            enableField(this.editRegionSchema.fields.parent)
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        mergeRegion() {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.region))
            this.mergeModel.secondary = null
            this.mergeRegionSchema.fields.primary.values = this.values
            this.mergeRegionSchema.fields.secondary.values = this.values
            enableField(this.mergeRegionSchema.fields.primary)
            enableField(this.mergeRegionSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        delRegion() {
            this.submitModel.region = JSON.parse(JSON.stringify(this.model.region))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.region.id == null) {
                axios.post(this.urls['region_post'], {
                    parent: this.submitModel.region.parent == null ? null : {
                        id: this.submitModel.region.parent.id,
                    },
                    individualName: this.submitModel.region.individualName,
                    individualHistoricalName: this.submitModel.region.individualHistoricalName,
                    pleiades: this.submitModel.region.pleiades,
                    isCity: this.submitModel.region.isCity,
                })
                    .then( (response) => {
                        this.submitModel.region = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the region.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.submitModel.region.parent) !== JSON.stringify(this.originalSubmitModel.region.parent)) {
                    if (this.submitModel.region.parent == null) {
                        data.parent = null
                    }
                    else {
                        data.parent = {
                            id: this.submitModel.region.parent.id
                        }
                    }
                }
                for (let key of ['individualName', 'individualHistoricalName', 'pleiades', 'isCity']) {
                    if (this.submitModel.region[key] !== this.originalSubmitModel.region[key]) {
                        data[key] = this.submitModel.region[key]
                    }
                }

                axios.put(this.urls['region_put'].replace('region_id', this.submitModel.region.id), data)
                    .then( (response) => {
                        this.submitModel.region = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the region.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.urls['region_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.region = response.data
                    this.update()
                    this.mergeAlerts = []
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.mergeModal = true
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the regions.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['region_delete'].replace('region_id', this.submitModel.region.id))
                .then( (response) => {
                    this.submitModel.region = null
                    this.submitModel.region = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the region.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['regions_get'])
                .then( (response) => {
                    this.values = response.data
                    this.regionSchema.fields.region.values = this.values
                    this.model.region = JSON.parse(JSON.stringify(this.submitModel.region))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the region data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        formatNameHistoricalName(regionWithParents) {
            return regionWithParents.name !== '' ? regionWithParents.name : '[' + regionWithParents.historicalName + ']'
        },
    }
}
</script>
