<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit offices">
                <editListRow
                    :schema="officeSchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.office,
                        del: model.office,
                    }"
                    @add="editOffice(true)"
                    @edit="editOffice()"
                    @del="delOffice()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editOfficeSchema"
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
            officeSchema: {
                fields: {
                    office: this.createMultiSelect('Office'),
                },
            },
            editOfficeSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Office name',
                        labelClasses: 'control-label',
                        model: 'office.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                office: null,
            },
            submitModel: {
                type: 'office',
                office: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Persons': {
                    depUrl: this.urls['person_deps_by_office'].replace('office_id', this.submitModel.office.id),
                    url: this.urls['person_get'],
                    urlIdentifier: 'person_id',
                }
            }
        },
    },
    mounted () {
        this.officeSchema.fields.office.values = this.values
        this.enableField(this.officeSchema.fields.office)
    },
    methods: {
        editOffice(add = false) {
            // TODO: check if name already exists
            this.submitModel = {
                type: 'office',
                office: null,
            }
            if (add) {
                this.submitModel.office =  {
                    name: null,
                }
            }
            else {
                this.submitModel.office = this.model.office
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        // TODO: merge
        delOffice() {
            this.submitModel.office = JSON.parse(JSON.stringify(this.model.office))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.office.id == null) {
                axios.post(this.urls['office_post'], {
                    name: this.submitModel.office.name
                })
                    .then( (response) => {
                        this.submitModel.office = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the office.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['office_put'].replace('office_id', this.submitModel.office.id), {
                    name: this.submitModel.office.name,
                })
                    .then( (response) => {
                        this.submitModel.office = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the office.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['office_delete'].replace('office_id', this.submitModel.office.id))
                .then( (response) => {
                    this.submitModel.office = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the office.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['offices_get'])
                .then( (response) => {
                    this.values = response.data
                    this.officeSchema.fields.office.values = this.values
                    this.model.office = JSON.parse(JSON.stringify(this.submitModel.office))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the office data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
