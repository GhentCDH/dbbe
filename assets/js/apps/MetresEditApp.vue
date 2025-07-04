<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit metres">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="metre"
                    :conditions="{
                        add: true,
                        edit: model.metre,
                        del: model.metre,
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
import axios from 'axios'

import AbstractListEdit from '../mixins/AbstractListEdit'
import {createMultiSelect,enableField} from "@/helpers/formFieldUtils";
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
            schema: {
                fields: {
                    metre: createMultiSelect('Metre'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'metre.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                metre: null,
            },
            submitModel: {
                submitType: 'metre',
                metre: {
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
                    depUrl: this.urls['occurrence_deps_by_metre'].replace('metre_id', this.submitModel.metre.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                'Types': {
                    depUrl: this.urls['type_deps_by_metre'].replace('metre_id', this.submitModel.metre.id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.metre.values = this.values
        enableField(this.schema.fields.metre)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.metre =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.metre = JSON.parse(JSON.stringify(this.model.metre))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.metre = this.model.metre
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.metre.id == null) {
                axios.post(this.urls['metre_post'], {
                    name: this.submitModel.metre.name,
                })
                    .then( (response) => {
                        this.submitModel.metre = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the metre.', login: isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['metre_put'].replace('metre_id', this.submitModel.metre.id), {
                    name: this.submitModel.metre.name,
                })
                    .then( (response) => {
                        this.submitModel.metre = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the metre.', login: isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['metre_delete'].replace('metre_id', this.submitModel.metre.id))
                .then( (response) => {
                    this.submitModel.metre = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the metre.', login: isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['metres_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.metre.values = this.values
                    this.model.metre = JSON.parse(JSON.stringify(this.submitModel.metre))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the metre data.', login: isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
