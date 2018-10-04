<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit management collections">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.management,
                        del: model.management,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @del="del()"
                />
            </panel>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
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
            @dismiss-alert="editAlerts.splice($event, 1)"
        />
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            :alerts="deleteAlerts"
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)"
        />
    </div>
</template>

<script>
import VueFormGenerator from 'vue-form-generator'

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

VueFormGenerator.validators.requiredMultiSelect = function (value, field, model) {
    if (value == null || value.length == 0) {
        return ['This fields is required!']
    }
    return []
}

export default {
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    management: this.createMultiSelect('Management'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'management.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                management: null,
            },
            submitModel: {
                submitType: 'management',
                management: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
            }
        },
    },
    mounted () {
        this.schema.fields.management.values = this.values
        this.enableField(this.schema.fields.management)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            this.submitModel = {
                submitType: 'management',
                management: null,
            }
            if (add) {
                this.submitModel.management =  {}
            }
            else {
                this.submitModel.management = JSON.parse(JSON.stringify(this.model.management))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.management = JSON.parse(JSON.stringify(this.model.management))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.management.id == null) {
                axios.post(this.urls['management_post'], {
                    name: this.submitModel.management.name,
                })
                    .then( (response) => {
                        this.submitModel.management = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the management.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (this.submitModel.management.name !== this.originalSubmitModel.management.name) {
                    data.name = this.submitModel.management.name
                }
                axios.put(this.urls['management_put'].replace('management_id', this.submitModel.management.id), data)
                    .then( (response) => {
                        this.submitModel.management = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the management collection.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['management_delete'].replace('management_id', this.submitModel.management.id))
                .then( (response) => {
                    this.submitModel.management = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the management collection.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['managements_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.management.values = this.values
                    this.model.management = JSON.parse(JSON.stringify(this.submitModel.management))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the management collection data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
