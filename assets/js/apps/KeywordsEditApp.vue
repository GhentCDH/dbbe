<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel :header="'Edit ' + (isSubject ? 'keywords' : 'tags')">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="keyword"
                    to-name="person"
                    :conditions="{
                        add: true,
                        edit: model.keyword,
                        del: model.keyword,
                        migrate: isSubject && model.keyword,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @migrate="migrate()"
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
        <migrateModal
            :show="migrateModal"
            :schema="migrateSchema"
            :migrate-model="migrateModel"
            :original-migrate-model="originalMigrateModel"
            :alerts="migrateAlerts"
            @cancel="cancelMigrate()"
            @reset="resetMigrate()"
            @confirm="submitMigrate()"
            @dismiss-alert="migrateAlerts.splice($event, 1)"
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
import axios from 'axios'

import { useListEdit } from '../Components/Edit/AbstractListEdit'
import {createMultiSelect,enableField} from "@/Components/FormFields/formFieldUtils";

export default {
    setup(props) {
      const listEdit = useListEdit(props.initUrls, props.initData)

      return {
        ...listEdit,
      }
    },
    props: {
        initPersons: {
            type: String,
            default: '',
        },
        initIsSubject: {
            type: String,
            default: 'true',
        },
    },
    data() {
        return {
            persons: JSON.parse(this.initPersons),
            isSubject: JSON.parse(this.initIsSubject),
            schema: {
                fields: {
                    keyword: createMultiSelect(JSON.parse(this.initIsSubject) ? 'Keyword' : 'Tag', {model: 'keyword'}),
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
            migrateSchema: {
                fields: {
                    primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            model: {
                keyword: null,
            },
            submitModel: {
                submitType: 'keyword',
                keyword: {
                    id: null,
                    name: null,
                }
            },
            migrateModel: {
                submitType: 'keyword',
                toType: 'person',
                primary: null,
                secondary: null,
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
                'Types': {
                    depUrl: this.urls['type_deps_by_keyword'].replace('keyword_id', this.submitModel.keyword.id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.keyword.values = this.values
        enableField(this.schema.fields.keyword)
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
        migrate() {
            this.migrateModel.primary = JSON.parse(JSON.stringify(this.model.keyword))
            this.migrateModel.secondary = null
            this.migrateSchema.fields.primary.values = this.values
            this.migrateSchema.fields.secondary.values = this.persons
            enableField(this.migrateSchema.fields.primary)
            this.migrateSchema.fields.primary.disabled = true
            enableField(this.migrateSchema.fields.secondary)
            this.originalMigrateModel = JSON.parse(JSON.stringify(this.migrateModel))
            this.migrateModal = true
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
                    isSubject: this.isSubject,
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
        submitMigrate() {
            this.migrateModal = false
            this.openRequests++
            axios.put(this.urls['keyword_migrate_person'].replace('primary_id', this.migrateModel.primary.id).replace('secondary_id', this.migrateModel.secondary.id))
                .then( (response) => {
                    this.submitModel.keyword = null
                    this.update()
                    this.migrateAlerts = []
                    this.alerts.push({type: 'success', message: 'Migration successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.migrateModal = true
                    this.migrateAlerts.push({type: 'error', message: 'Something went wrong while migrating the keyword.', login: this.isLoginError(error)})
                    console.log(error)
                })
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
