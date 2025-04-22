<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit acknowledgements">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="acknowledgement"
                    :conditions="{
                        add: true,
                        edit: model.acknowledgement,
                        del: model.acknowledgement,
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

import AbstractField from '@/Components/FormFields/AbstractField'
import AbstractListEdit from '@/Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    acknowledgement: this.createMultiSelect('Acknowledgement'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'acknowledgement.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                acknowledgement: null,
            },
            submitModel: {
                submitType: 'acknowledgement',
                acknowledgement: {
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
                    depUrl: this.urls['occurrence_deps_by_acknowledgement'].replace('acknowledgement_id', this.submitModel.acknowledgement.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                'Types': {
                    depUrl: this.urls['type_deps_by_acknowledgement'].replace('acknowledgement_id', this.submitModel.acknowledgement.id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.acknowledgement.values = this.values
        this.enableField(this.schema.fields.acknowledgement)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.acknowledgement =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.acknowledgement = JSON.parse(JSON.stringify(this.model.acknowledgement))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.acknowledgement = this.model.acknowledgement
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.acknowledgement.id == null) {
                axios.post(this.urls['acknowledgement_post'], {
                    name: this.submitModel.acknowledgement.name,
                })
                    .then( (response) => {
                        this.submitModel.acknowledgement = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the acknowledgement.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['acknowledgement_put'].replace('acknowledgement_id', this.submitModel.acknowledgement.id), {
                    name: this.submitModel.acknowledgement.name,
                })
                    .then( (response) => {
                        this.submitModel.acknowledgement = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the acknowledgement.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['acknowledgement_delete'].replace('acknowledgement_id', this.submitModel.acknowledgement.id))
                .then( (response) => {
                    this.submitModel.acknowledgement = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the acknowledgement.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['acknowledgements_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.acknowledgement.values = this.values
                    this.model.acknowledgement = JSON.parse(JSON.stringify(this.submitModel.acknowledgement))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the acknowledgement data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
