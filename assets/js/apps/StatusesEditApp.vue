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
                <div class="panel-heading">Edit status</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-10">
                            <vue-form-generator
                                :schema="statusTypeSchema"
                                :model="model" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-10">
                            <vue-form-generator
                                :schema="statusSchema"
                                :model="model" />
                        </div>
                        <div class="col-xs-2 ptop-default">
                            <a
                                v-if="model.statusType"
                                href="#"
                                class="action"
                                title="Add a new status"
                                @click.prevent="editStatus(true)">
                                <i class="fa fa-plus" />
                            </a>
                            <a
                                v-if="model.status"
                                href="#"
                                class="action"
                                title="Edit the selected status"
                                @click.prevent="editStatus()">
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                v-if="model.status"
                                href="#"
                                class="action"
                                title="Delete the selected status"
                                @click.prevent="delStatus()">
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
                :schema="editStatusSchema"
                :model="editModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <div slot="header">
                <h4
                    v-if="editModel && editModel.id"
                    class="modal-title">
                    Edit status {{ editModal.name }}
                </h4>
                <h4
                    v-else
                    class="modal-title">
                    Add a new status
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
                    {{ (editModel.status && editModel.status.id) ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            size="lg"
            auto-focus>
            <div v-if="Object.keys(delDependencies).length !== 0">
                <p>This status has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies.manuscripts"
                        :key="dependency.id">
                        Manuscript
                        <a :href="getManuscriptUrl.replace('manuscript_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                </ul>
            </div>
            <div v-else-if="delModel">
                <p>Are you sure you want to delete status "{{ delModel.name }}"?</p>
            </div>
            <div slot="header">
                <h4
                    v-if="delModel"
                    class="modal-title">
                    Delete status {{ delModel.name }}
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
        initStatuses: {
            type: String,
            default: '',
        },
        getStatusesUrl: {
            type: String,
            default: '',
        },
        getManuscriptDepsByStatusUrl: {
            type: String,
            default: '',
        },
        getManuscriptUrl: {
            type: String,
            default: '',
        },
        postStatusUrl: {
            type: String,
            default: '',
        },
        putStatusUrl: {
            type: String,
            default: '',
        },
        deleteStatusUrl: {
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
                status: null,
            },
            editModal: false,
            editModel: {
                status: null,
                statusType: null,
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
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidEditForm: true,
            model: {
                status: null,
            },
            openRequests: 0,
            originalEditModel: {},
            originalMergeModel: {},
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
            statusValues: JSON.parse(this.initStatuses),
        }
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
            this.editModel = {
                status: null,
                statusType: null,
            }
            this.editModel.statusType = this.model.statusType
            this.loadStatusTypeField(this.editStatusSchema.fields.statusType)
            if (add) {
                this.editModel.status =  {
                    name: null,
                    type: this.model.statusType.id,
                }
            }
            else {
                this.editModel.status = this.model.status
            }
            this.originalEditModel = JSON.parse(JSON.stringify(this.editModel))
            this.editModal = true
        },
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
        resetEdit() {
            this.editModel = JSON.parse(JSON.stringify(this.originalEditModel))
        },
        delStatus() {
            this.delModel = this.model.status
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            // TODO: add occurrence and type dependencies
            axios.all([
                axios.get(this.getManuscriptDepsByStatusUrl.replace('status_id', this.delModel.id)),
            ])
                .then((results) => {
                    this.delDependencies = {}
                    if (results[0].data.length > 0) {
                        this.delDependencies.manuscripts = results[0].data
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
                axios.post(this.postStatusUrl, {
                    type: this.editModel.status.type,
                    name: this.editModel.status.name,
                })
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while adding the status.'})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.putStatusUrl.replace('status_id', this.editModel.id), {
                    name: this.editModel.status.name,
                })
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while updating the status.'})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.delModal = false
            this.openRequests++
            axios.delete(this.deleteStatusUrl.replace('status_id', this.delModel.id))
                .then( (response) => {
                    this.delModel = null
                    this.editModel = null
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.update()
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the status.'})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.getStatusesUrl)
                .then( (response) => {
                    this.statusValues = response.data
                    this.loadStatusField()
                    this.model.status = this.editModel
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while renewing the status data.'})
                    console.log(error)
                })
        },
        loadStatusTypeField(field) {
            let statusTypes = ['manuscript', 'occurrence_record', 'occurrence_text', 'type_text']
            field.values = statusTypes
                .map((statusType) => {
                    return {
                        id: statusType,
                        name: this.formatStatusType(statusType)
                    }
                })
        },
        loadStatusField() {
            this.statusSchema.fields.status.values = this.statusValues
                .filter((status) => status.type === this.model.statusType.id)
        },
        formatStatusType(statusType) {
            // Capitalize and replace underscores by spaces
            return (statusType.charAt(0).toUpperCase() + statusType.substr(1)).replace('_', ' ')
        },
    }
}
</script>
