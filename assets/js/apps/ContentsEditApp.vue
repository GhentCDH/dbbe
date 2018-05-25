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
                <div class="panel-heading">Edit content</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-10">
                            <vue-form-generator
                                :schema="contentSchema"
                                :model="model" />
                        </div>
                        <div class="col-xs-2 ptop-default">
                            <a
                                href="#"
                                class="action"
                                title="Add a new content"
                                @click.prevent="editContent(true)">
                                <i class="fa fa-plus" />
                            </a>
                            <a
                                v-if="model.content"
                                href="#"
                                class="action"
                                title="Edit the selected content"
                                @click.prevent="editContent()">
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                v-if="model.content"
                                href="#"
                                class="action"
                                title="Merge the selected content with another content"
                                @click.prevent="mergeContent()">
                                <i class="fa fa-compress" />
                            </a>
                            <a
                                v-if="model.content"
                                href="#"
                                class="action"
                                title="Delete the selected content"
                                @click.prevent="delContent()">
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
                :schema="editContentSchema"
                :model="editModel"
                :options="formOptions"
                @validated="editFormValidated" />
            <div slot="header">
                <h4
                    v-if="editModel && editModel.id"
                    class="modal-title">
                    Edit content {{ editModal.name }}
                </h4>
                <h4
                    v-else
                    class="modal-title">
                    Add a new content
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
                    {{ (editModel.id) ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="mergeModal"
            size="lg"
            auto-focus>
            <vue-form-generator
                :schema="mergeContentSchema"
                :model="mergeModel"
                :options="formOptions"
                @validated="mergeFormValidated" />
            <div slot="header">
                <h4 class="modal-title">
                    Merge content
                </h4>
            </div>
            <div slot="footer">
                <btn @click="mergeModal=false">Cancel</btn>
                <btn
                    :disabled="JSON.stringify(mergeModel) === JSON.stringify(originalMergeModel)"
                    type="warning"
                    @click="resetMerge()">
                    Reset
                </btn>
                <btn
                    v-if="editModel"
                    type="success"
                    :disabled="invalidMergeForm"
                    @click="submitMerge()">
                    Merge
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            size="lg"
            auto-focus>
            <div v-if="Object.keys(delDependencies).length !== 0">
                <p>This content has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies.manuscripts"
                        :key="dependency.id">
                        Manuscript
                        <a :href="getManuscriptUrl.replace('manuscript_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                    <li
                        v-for="dependency in delDependencies.contents"
                        :key="dependency.id">
                        Content
                        <!-- <a :href="getContentUrl.replace('content_id', dependency.id)">{{ dependency.name }}</a> -->
                        {{ dependency.name }}
                    </li>
                </ul>
            </div>
            <div v-else-if="delModel">
                <p>Are you sure you want to delete content "{{ delModel.name }}"?</p>
            </div>
            <div slot="header">
                <h4
                    v-if="delModel"
                    class="modal-title">
                    Delete content {{ delModel.name }}
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
        initContents: {
            type: String,
            default: '',
        },
        getContentsUrl: {
            type: String,
            default: '',
        },
        getManuscriptDepsByContentUrl: {
            type: String,
            default: '',
        },
        getManuscriptUrl: {
            type: String,
            default: '',
        },
        getContentsByContentUrl: {
            type: String,
            default: '',
        },
        postContentUrl: {
            type: String,
            default: '',
        },
        putContentUrl: {
            type: String,
            default: '',
        },
        putContentMergeUrl: {
            type: String,
            default: '',
        },
        deleteContentUrl: {
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
                content: null,
            },
            editModal: false,
            editModel: {
                id: null,
                parent: null,
                name: null,
            },
            editContentSchema: {
                fields: {
                    parent: this.createMultiSelect('Parent'),
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Content name',
                        labelClasses: 'control-label',
                        model: 'individualName',
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
            invalidMergeForm: true,
            mergeModal: false,
            mergeModel: {
                primary: null,
                secondary: null,
            },
            mergeContentSchema: {
                fields: {
                    primary: this.createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: this.createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            model: {
                content: null,
            },
            openRequests: 0,
            originalEditModel: {},
            originalMergeModel: {},
            contentSchema: {
                fields: {
                    content: this.createMultiSelect('Content'),
                },
            },
            contentValues: JSON.parse(this.initContents),
        }
    },
    watch: {
        'model.content'() {
            // set full parent, so the name can be formatted correctly
            if (this.model.content != null && this.model.content.parent != null) {
                this.model.content.parent = this.contentValues.filter((contentWithParents) => contentWithParents.id === this.model.content.parent.id)[0]
            }
        },
    },
    mounted () {
        this.contentSchema.fields.content.values = this.contentValues
        this.enableField(this.contentSchema.fields.content)
    },
    methods: {
        editContent(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.editModel =  {
                    id: null,
                    name: null,
                    parent: this.model.content,
                    individualName: null,
                }
            }
            else {
                this.editModel = this.model.content
            }
            this.editContentSchema.fields.parent.values = this.contentValues
                .filter(content => content.id != this.editModel.id) // Remove current content
            this.enableField(this.editContentSchema.fields.parent)
            this.originalEditModel = JSON.parse(JSON.stringify(this.editModel))
            this.editModal = true
        },
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
        resetEdit() {
            this.editModel = JSON.parse(JSON.stringify(this.originalEditModel))
        },
        mergeContent() {
            this.mergeModel.primary = this.model.content
            this.mergeModel.secondary = null
            this.mergeContentSchema.fields.primary.values = this.contentValues
            this.mergeContentSchema.fields.secondary.values = this.contentValues
            this.enableField(this.mergeContentSchema.fields.primary)
            this.enableField(this.mergeContentSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        mergeFormValidated(isValid, errors) {
            this.invalidMergeForm = !isValid
        },
        resetMerge() {
            this.mergeModel = JSON.parse(JSON.stringify(this.originalMergeModel))
        },
        delContent() {
            this.delModel = this.model.content
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.all([
                axios.get(this.getManuscriptDepsByContentUrl.replace('content_id', this.delModel.id)),
                axios.get(this.getContentsByContentUrl.replace('content_id', this.delModel.id)),
            ])
                .then((results) => {
                    this.delDependencies = {}
                    if (results[0].data.length > 0) {
                        this.delDependencies.manuscripts = results[0].data
                    }
                    if (results[1].data.length > 0) {
                        this.delDependencies.contents = results[1].data
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
                axios.post(this.postContentUrl, {
                    parent: this.editModel.parent == null ? null : {
                        id: this.editModel.parent.id,
                    },
                    individualName: this.editModel.individualName,
                })
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while adding the content.'})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.editModel.parent) !== JSON.stringify(this.originalEditModel.parent)) {
                    if (this.editModel.parent == null) {
                        data.parent = null
                    }
                    else {
                        data.parent = {
                            id: this.editModel.parent.id
                        }
                    }
                }
                if (this.editModel.individualName !== this.originalEditModel.individualName) {
                    data.individualName = this.editModel.individualName
                }
                if (this.editModel.individualHistoricalName !== this.originalEditModel.individualHistoricalName) {
                    data.individualHistoricalName = this.editModel.individualHistoricalName
                }
                if (this.editModel.pleiades !== this.originalEditModel.pleiades) {
                    data.pleiades = this.editModel.pleiades
                }
                if (this.editModel.isCity !== this.originalEditModel.isCity) {
                    data.isCity = this.editModel.isCity
                }
                axios.put(this.putContentUrl.replace('content_id', this.editModel.id), data)
                    .then( (response) => {
                        this.editModel = response.data
                        this.update()
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.alerts.push({type: 'error', message: 'Something whent wrong while updating the content.'})
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.putContentMergeUrl.replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.editModel = response.data
                    this.update()
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the content.'})
                    console.log(error)
                })
        },
        submitDelete() {
            this.delModal = false
            this.openRequests++
            axios.delete(this.deleteContentUrl.replace('content_id', this.delModel.id))
                .then( (response) => {
                    this.delModel = null
                    this.editModel = null
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.update()
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the content.'})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.getContentsUrl)
                .then( (response) => {
                    this.contentValues = response.data
                    this.contentSchema.fields.content.values = this.contentValues
                    this.model.content = this.editModel
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while renewing the content data.'})
                    console.log(error)
                })
        },
    }
}
</script>
