<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit contents">
                <editListRow
                    :schema="contentSchema"
                    :model="model"
                    name="content"
                    :conditions="{
                        add: true,
                        edit: model.content,
                        merge: model.content,
                        del: model.content,
                    }"
                    @add="editContent(true)"
                    @edit="editContent()"
                    @merge="mergeContent()"
                    @del="delContent()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            ref="editModal"
            :show="editModal"
            :schema="editContentSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
        <mergeModal
            :show="mergeModal"
            :schema="mergeContentSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge()"
            @confirm="submitMerge()"
            @dismiss-alert="mergeAlerts.splice($event, 1)">
            <table
                v-if="mergeModel.primary && mergeModel.secondary"
                slot="preview"
                class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Name</td>
                        <td>{{ mergeModel.primary.name || mergeModel.secondary.name }}</td>
                    </tr>
                </tbody>
            </table>
        </mergeModal>
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
    props: {
        initPersons: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            persons: JSON.parse(this.initPersons),
            contentSchema: {
                fields: {
                    content: this.createMultiSelect('Content'),
                },
            },
            editContentSchema: {
                fields: {
                    parent: this.createMultiSelect('Parent', {model: 'content.parent'}),
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Content name',
                        labelClasses: 'control-label',
                        model: 'content.individualName',
                        validator: [VueFormGenerator.validators.string, this.validateIndividualNameOrPerson],
                    },
                    individualPerson: this.createMultiSelect('Person', {model: 'content.individualPerson', validator: this.validateIndividualNameOrPerson}),
                },
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
            submitModel: {
                submitType: 'content',
                content: {
                    id: null,
                    parent: null,
                    name: null,
                    individualPerson: null,
                }
            },
            mergeModel: {
                submitType: 'contents',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function () {
            return {
                'Contents': {
                    depUrl: this.urls['content_deps_by_content'].replace('content_id', this.submitModel.content.id),
                },
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_content'].replace('content_id', this.submitModel.content.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
            }
        },
    },
    watch: {
        'model.content'() {
            // set full parent, so the name can be formatted correctly
            if (this.model.content != null && this.model.content.parent != null) {
                this.model.content.parent = this.values.filter((contentWithParents) => contentWithParents.id === this.model.content.parent.id)[0]
            }
        },
        // reset parent to null if nothing is selected
        'submitModel.content.parent'() {
            if (Array.isArray(this.submitModel.content.parent) && this.submitModel.content.parent.length == 0) {
                this.submitModel.content.parent = null
            }
        },
        // reset parent to null if nothing is entered
        'submitModel.content.individualName'() {
            this.$refs.editModal.revalidate()
            if (this.submitModel.content.individualName == '') {
                this.submitModel.content.individualName = null
            }
        },
        // reset individualPerson to null if nothing is selected
        'submitModel.content.individualPerson'() {
            this.$refs.editModal.revalidate()
            if (Array.isArray(this.submitModel.content.individualPerson) && this.submitModel.content.individualPerson.length == 0) {
                this.submitModel.content.individualPerson = null
            }
        },
    },
    mounted () {
        this.contentSchema.fields.content.values = this.values
        this.enableField(this.contentSchema.fields.content)
        if (window.location.href.split('?').length === 2) {
            const paramsString = window.location.href.split('?')[1]
            const params = paramsString.split('&')
            for (const param of params) {
                const split = param.split('=')
                if (
                    split.length === 2 &&
                    split[0] === 'id' &&
                    /^\d+$/.test(split[1])
                ) {
                    const id = parseInt(split[1])
                    const filteredValues = this.values.filter(content => content.id === id)
                    if (filteredValues.length === 1) {
                        this.model.content = filteredValues[0]
                    }
                }
            }
        }
    },
    methods: {
        editContent(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.content =  {
                    id: null,
                    name: null,
                    parent: this.model.content,
                    individualName: null,
                    individualPerson: null,
                }
            }
            else {
                this.submitModel.content = JSON.parse(JSON.stringify(this.model.content))
            }
            this.editContentSchema.fields.parent.values = this.values
                .filter((content) => !this.isOrIsChild(content, this.model.content)) // Remove values that create cycles
            this.enableField(this.editContentSchema.fields.parent)
            this.editContentSchema.fields.individualPerson.values = this.persons
            this.enableField(this.editContentSchema.fields.individualPerson)
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        mergeContent() {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.content))
            this.mergeModel.secondary = null
            this.mergeContentSchema.fields.primary.values = this.values
            this.mergeContentSchema.fields.secondary.values = this.values
            this.enableField(this.mergeContentSchema.fields.primary)
            this.enableField(this.mergeContentSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        delContent() {
            this.submitModel.content = this.model.content
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.content.id == null) {
                axios.post(this.urls['content_post'], {
                    parent: this.submitModel.content.parent == null ? null : {
                        id: this.submitModel.content.parent.id,
                    },
                    individualName: this.submitModel.content.individualName,
                    individualPerson: this.submitModel.content.individualPerson == null ? null : {
                        id: this.submitModel.content.individualPerson.id,
                    }
                })
                    .then( (response) => {
                        this.submitModel.content = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the content.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.submitModel.content.parent) !== JSON.stringify(this.originalSubmitModel.content.parent)) {
                    if (this.submitModel.content.parent == null) {
                        data.parent = null
                    }
                    else {
                        data.parent = {
                            id: this.submitModel.content.parent.id
                        }
                    }
                }
                if (this.submitModel.content.individualName !== this.originalSubmitModel.content.individualName) {
                    data.individualName = this.submitModel.content.individualName
                }
                if (JSON.stringify(this.submitModel.content.individualPerson) !== JSON.stringify(this.originalSubmitModel.content.individualPerson)) {
                    if (this.submitModel.content.individualPerson == null) {
                        data.individualPerson = null
                    }
                    else {
                        data.individualPerson = {
                            id: this.submitModel.content.individualPerson.id
                        }
                    }
                }
                axios.put(this.urls['content_put'].replace('content_id', this.submitModel.content.id), data)
                    .then( (response) => {
                        this.submitModel.content = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the content.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.urls['content_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.content = response.data
                    this.update()
                    this.mergeAlerts = []
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.mergeModal = true
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the content.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['content_delete'].replace('content_id', this.submitModel.content.id))
                .then( (response) => {
                    this.submitModel.content = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the content.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['contents_get'])
                .then( (response) => {
                    this.values = response.data
                    this.contentSchema.fields.content.values = this.values
                    this.model.content = JSON.parse(JSON.stringify(this.submitModel.content))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the content data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        validateIndividualNameOrPerson() {
            if (
                (
                    this.submitModel.content.individualName == null
                    && this.submitModel.content.individualPerson == null
                ) || (
                    this.submitModel.content.individualName != null
                    && this.submitModel.content.individualPerson != null
                )
            ) {
                return ['Please provide a content name or select a person (but not both).']
            }
            return []
        },
    }
}
</script>
