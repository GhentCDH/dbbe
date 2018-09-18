<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit keywords">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="keyword"
                    :conditions="{
                        add: true,
                        edit: model.keyword,
                        del: model.keyword,
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
                    keyword: this.createMultiSelect('Keyword'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'keyword.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                keyword: null,
            },
            submitModel: {
                type: 'keyword',
                keyword: {
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
                    depUrl: this.urls['occurrence_deps_by_keyword'].replace('keyword_id', this.submitModel.keyword.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.keyword.values = this.values
        this.enableField(this.schema.fields.keyword)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.keyword =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.keyword = JSON.parse(JSON.stringify(this.model.keyword))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.keyword = this.model.keyword
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.keyword.id == null) {
                axios.post(this.urls['keyword_post'], {
                    name: this.submitModel.keyword.name,
                })
                    .then( (response) => {
                        this.submitModel.keyword = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the keyword.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['keyword_put'].replace('keyword_id', this.submitModel.keyword.id), {
                    name: this.submitModel.keyword.name,
                })
                    .then( (response) => {
                        this.submitModel.keyword = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the keyword.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['keyword_delete'].replace('keyword_id', this.submitModel.keyword.id))
                .then( (response) => {
                    this.submitModel.keyword = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the keyword.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['keywords_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.keyword.values = this.values
                    this.model.keyword = JSON.parse(JSON.stringify(this.submitModel.keyword))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the keyword data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
