<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit roles">
                <editListRow
                    :schema="roleSchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.role,
                        del: model.role,
                    }"
                    @add="editRole(true)"
                    @edit="editRole()"
                    @del="delRole()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editRoleSchema"
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
            roleSchema: {
                fields: {
                    role: this.createMultiSelect('Role'),
                },
            },
            editRoleSchema: {
                fields: {
                    usage: this.createMultiSelect(
                        'Usage',
                        {
                            model: 'role.usage',
                            values: [
                                {id: 'article', name: 'Article'},
                                {id: 'book', name: 'Book'},
                                {id: 'bookChapter', name: 'Book chapter'},
                                {id: 'manuscript', name: 'Manuscript'},
                                {id: 'occurrence', name: 'Occurrence'},
                                {id: 'type', name: 'Type'}
                            ],
                            required: true,
                            validator: VueFormGenerator.validators.requiredMultiSelect,
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                    // TODO: systemName niet aanpasbaar maken
                    systemName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'System name',
                        labelClasses: 'control-label',
                        model: 'role.systemName',
                        required: true,
                        validator: VueFormGenerator.validators.regexp,
                        pattern: '^[a-z]+$',
                        hint: 'Only use small lower cases'
                    },
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Role name',
                        labelClasses: 'control-label',
                        model: 'role.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                role: null,
            },
            submitModel: {
                submitType: 'role',
                role: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_role'].replace('role_id', this.submitModel.role.id),
                    url: this.urls['manuscrip_get'],
                    urlIdentifier: 'manuscript_id',
                }
                // TODO: occurrences, types, articles, books, bookChapters
            }
        },
    },
    mounted () {
        this.roleSchema.fields.role.values = this.values
        this.enableField(this.roleSchema.fields.role)
    },
    methods: {
        editRole(add = false) {
            // TODO: check if systemName already exists
            this.submitModel = {
                submitType: 'role',
                role: null,
            }
            if (add) {
                this.submitModel.role =  {
                    name: null,
                }
            }
            else {
                this.submitModel.role = JSON.parse(JSON.stringify(this.model.role))
                this.submitModel.role.usage = this.model.role.usage.map(item =>
                    this.editRoleSchema.fields.usage.values.filter(v => v.id === item)[0]
                )
            }
            this.enableField(this.editRoleSchema.fields.usage)
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delRole() {
            this.submitModel.role = JSON.parse(JSON.stringify(this.model.role))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.role.id == null) {
                axios.post(this.urls['role_post'], {
                    usage: this.submitModel.role.usage == null ? null : this.submitModel.role.usage.map(item => item.id),
                    systemName: this.submitModel.role.systemName,
                    name: this.submitModel.role.name,
                })
                    .then( (response) => {
                        this.submitModel.role = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the role.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.submitModel.role.usage) !== JSON.stringify(this.originalSubmitModel.role.usage)) {
                    data.usage = this.submitModel.role.usage.map(item => item.id)
                }
                if (this.submitModel.role.systemName !== this.originalSubmitModel.role.systemName) {
                    data.systemName = this.submitModel.role.systemName
                }
                if (this.submitModel.role.name !== this.originalSubmitModel.role.name) {
                    data.name = this.submitModel.role.name
                }
                axios.put(this.urls['role_put'].replace('role_id', this.submitModel.role.id), data)
                    .then( (response) => {
                        this.submitModel.role = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the role.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['role_delete'].replace('role_id', this.submitModel.role.id))
                .then( (response) => {
                    this.submitModel.role = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the role.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['roles_get'])
                .then( (response) => {
                    this.values = response.data
                    this.roleSchema.fields.role.values = this.values
                    this.model.role = JSON.parse(JSON.stringify(this.submitModel.role))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the role data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
