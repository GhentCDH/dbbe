<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel
                header="Edit origins"
                :links="[{
                    url: urls['regions_edit'],
                    text: 'Add, edit or delete regions',
                }]"
            >
                <editListRow
                    :schema="regionSchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        edit: model.regionWithParents,
                    }"
                    @edit="editRegion()" />
                <editListRow
                    :schema="monasterySchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: model.regionWithParents,
                        edit: model.institution,
                        del: model.institution,
                    }"
                    @add="editMonastery(true)"
                    @edit="editMonastery()"
                    @del="delMonastery()" />
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
  createMultiSelect,
  dependencyField,
  enableField,
  loadLocationField
} from "@/helpers/formFieldUtils";
import {isLoginError} from "@/helpers/errorUtil";
import Edit from "@/Components/Edit/Modals/Edit.vue";
import Merge from "@/Components/Edit/Modals/Merge.vue";
import Delete from "@/Components/Edit/Modals/Delete.vue";

export default {
    mixins: [
        AbstractListEdit,
    ],
    components: {
      editModal: Edit,
      mergeModal: Merge,
      deleteModal: Delete
    },
    data() {
        return {
            regionSchema: {
                fields: {
                    region: createMultiSelect('Region', {model: 'regionWithParents'}, {customLabel: this.formatHistoricalName}),
                }
            },
            monasterySchema: {
                fields: {
                    monastery: createMultiSelect('Monastery', {model: 'institution', dependency: 'regionWithParents', dependencyName: 'region'}),
                }
            },
            editRegionSchema: {
                fields: {
                    individualHistoricalName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Region name',
                        labelClasses: 'control-label',
                        model: 'regionWithParents.individualHistoricalName',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editMonasterySchema: {
                fields: {
                    region: createMultiSelect('Region', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}, {customLabel: this.formatHistoricalName}),
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
            model: {
                regionWithParents: null,
                institution: null,
            },
            submitModel: {
                submitType: null,
                regionWithParents: null,
                institution: null,
            },
        }
    },
    computed: {
        editSchema: function() {
            switch (this.submitModel.submitType) {
            case 'regionWithParents':
                return this.editRegionSchema
                break;
            default:
                return this.editMonasterySchema
            }
        },
        depUrls: function() {
            return {
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_institution'].replace('institution_id', this.submitModel.institution.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                }
            }
        },
    },
    watch: {
        'model.regionWithParents'() {
            if (this.model.regionWithParents == null) {
                dependencyField(this.monasterySchema.fields.monastery, this.model)
            }
            else {
                loadLocationField(this.monasterySchema.fields.monastery, this.model,this.values)
                enableField(this.monasterySchema.fields.monastery, this.model)
            }
        },
    },
    mounted () {
        loadLocationField(this.regionSchema.fields.region, this.model,this.values)
        enableField(this.regionSchema.fields.region, this.model)
        dependencyField(this.monasterySchema.fields.monastery, this.model)
    },
    methods: {
        editRegion() {
            this.submitModel.submitType = 'regionWithParents'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        editMonastery(add = false) {
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

            loadLocationField(this.editMonasterySchema.fields.region, this.model, this.values)
            enableField(this.editMonasterySchema.fields.region, this.model)

            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delMonastery() {
            this.submitModel.submitType = 'institution'
            this.submitModel.regionWithParents = JSON.parse(JSON.stringify(this.model.regionWithParents))
            this.submitModel.institution = JSON.parse(JSON.stringify(this.model.institution))
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
                    individualHistoricalName: this.submitModel.regionWithParents.individualHistoricalName,
                }
                break
            case 'institution':
                if (this.submitModel.institution.id == null) {
                    url = this.urls['monastery_post']
                    data = {
                        name: this.submitModel.institution.name,
                        regionWithParents: {
                            id: this.submitModel.regionWithParents.id
                        }
                    }
                }
                else {
                    url = this.urls['monastery_put'].replace('monastery_id', this.submitModel.institution.id)
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
                        }
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding a ' + this.formatType(this.submitModel.submitType) + '.', login: isLoginError(error)})
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
                        }
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the ' + this.formatType(this.submitModel.submitType) + '.', login: isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['monastery_delete'].replace('monastery_id', this.submitModel.institution.id))
                .then( (response) => {
                    this.submitModel.institution = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the ' + this.formatType(this.submitModel.submitType) + '.', login: isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['origins_get'])
                .then( (response) => {
                    this.values = response.data
                    switch(this.submitModel.submitType) {
                    case 'regionWithParents':
                        this.model.regionWithParents = JSON.parse(JSON.stringify(this.submitModel.regionWithParents))
                        loadLocationField(this.regionSchema.fields.region, this.model, this.values)
                        break
                    case 'institution':
                        this.model.regionWithParents = JSON.parse(JSON.stringify(this.submitModel.regionWithParents))
                        this.model.institution = JSON.parse(JSON.stringify(this.submitModel.institution))
                        loadLocationField(this.regionSchema.fields.region, this.model, this.values)
                        loadLocationField(this.monasterySchema.fields.monastery, this.model, this.values)
                        enableField(this.monasterySchema.fields.monastery, this.model)
                        break
                    }
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the origin data.', login: isLoginError(error)})
                    console.log(error)
                })
        },
        formatType(type) {
            if (type === 'regionWithParents') {
                return 'region'
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
