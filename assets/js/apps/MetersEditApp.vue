<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit meters">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="meter"
                    :conditions="{
                        add: true,
                        edit: model.meter,
                        del: model.meter,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @del="del()" />
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
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
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

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    meter: this.createMultiSelect('Meter'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'meter.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                meter: null,
            },
            submitModel: {
                type: 'meter',
                meter: {
                    id: null,
                    name: null,
                }
            },
        }
    },
    computed: {
        depUrls: function () {
            return {
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_meter'].replace('meter_id', this.submitModel.meter.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                'Types': {
                    depUrl: this.urls['type_deps_by_meter'].replace('meter_id', this.submitModel.meter.id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.meter.values = this.values
        this.enableField(this.schema.fields.meter)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.meter =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.meter = JSON.parse(JSON.stringify(this.model.meter))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.meter = this.model.meter
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.meter.id == null) {
                axios.post(this.urls['meter_post'], {
                    name: this.submitModel.meter.name,
                })
                    .then( (response) => {
                        this.submitModel.meter = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the meter.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['meter_put'].replace('meter_id', this.submitModel.meter.id), {
                    name: this.submitModel.meter.name,
                })
                    .then( (response) => {
                        this.submitModel.meter = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the meter.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['meter_delete'].replace('meter_id', this.submitModel.meter.id))
                .then( (response) => {
                    this.submitModel.meter = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the meter.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['meters_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.meter.values = this.values
                    this.model.meter = JSON.parse(JSON.stringify(this.submitModel.meter))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the meter data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
