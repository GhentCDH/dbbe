<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit statuses">
                <editListRow
                    :schema="statusTypeSchema"
                    :model="model" />
                <editListRow
                    :schema="statusSchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: model.statusType,
                        edit: model.status,
                        del: model.status,
                    }"
                    @add="editStatus(true)"
                    @edit="editStatus()"
                    @del="delStatus()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editStatusSchema"
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
            statusTypeSchema: {
                fields: {
                    statusType: this.createMultiSelect('Status Type', {model: 'statusType'}),
                },
            },
            statusSchema: {
                fields: {
                    status: this.createMultiSelect('Status', {dependency: 'statusType', dependencyName: 'status type'}),
                },
            },
            editStatusSchema: {
                fields: {
                    statusType: this.createMultiSelect('Status Type', {model: 'statusType'}, {loading: false}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Status name',
                        labelClasses: 'control-label',
                        model: 'status.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                statusType: null,
                status: null,
            },
            submitModel: {
                type: 'status',
                statusType: null,
                status: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                // TODO: add occurrence and type dependencies
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_status'].replace('status_id', this.submitModel.status.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                }
            }
        },
    },
    watch: {
        'model.statusType'() {
            if (this.model.statusType == null) {
                this.dependencyField(this.statusSchema.fields.status)
            }
            else {
                this.loadStatusField()
                this.enableField(this.statusSchema.fields.status)
            }
        },
    },
    mounted () {
        this.loadStatusTypeField(this.statusTypeSchema.fields.statusType)
        this.enableField(this.statusTypeSchema.fields.statusType)
        this.dependencyField(this.statusSchema.fields.status)
    },
    methods: {
        editStatus(add = false) {
            // TODO: check if name already exists
            this.submitModel = {
                type: 'status',
                statusType: null,
                status: null,
            }
            this.submitModel.statusType = this.model.statusType
            this.loadStatusTypeField(this.editStatusSchema.fields.statusType)
            if (add) {
                this.submitModel.status =  {
                    name: null,
                    type: this.model.statusType.id,
                }
            }
            else {
                this.submitModel.status = JSON.parse(JSON.stringify(this.model.status))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delStatus() {
            this.submitModel.status = JSON.parse(JSON.stringify(this.model.status))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.status.id == null) {
                axios.post(this.urls['status_post'], {
                    type: this.submitModel.status.type,
                    name: this.submitModel.status.name,
                })
                    .then( (response) => {
                        this.submitModel.status = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the status.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['status_put'].replace('status_id', this.submitModel.status.id), {
                    name: this.submitModel.status.name,
                })
                    .then( (response) => {
                        this.submitModel.status = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the status.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['status_delete'].replace('status_id', this.submitModel.status.id))
                .then( (response) => {
                    this.submitModel.status = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the status.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['statuses_get'])
                .then( (response) => {
                    this.values = response.data
                    this.loadStatusField()
                    this.model.status = JSON.parse(JSON.stringify(this.submitModel.status))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the status data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        loadStatusTypeField(field) {
            let statusTypes = ['manuscript', 'occurrence_record', 'occurrence_text', 'occurrence_divided','type_text']
            field.values = statusTypes
                .map((statusType) => {
                    return {
                        id: statusType,
                        name: this.formatStatusType(statusType)
                    }
                })
        },
        loadStatusField() {
            this.statusSchema.fields.status.values = this.values
                .filter((status) => status.type === this.model.statusType.id)
        },
        formatStatusType(statusType) {
            // Capitalize and replace underscores by spaces
            return (statusType.charAt(0).toUpperCase() + statusType.substr(1)).replace('_', ' ')
        },
    }
}
</script>
