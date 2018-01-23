<template>
    <div>
        <article class="col-xs-12">
            <h2>Users</h2>
            <button class="btn btn-success" @click="create"><i class="fa fa-user-plus"></i>Add a new user</button>
            <v-server-table
                url="/admin/users"
                ref="table"
                :columns="['username', 'email', 'full name', 'roles', 'status', 'created', 'modified', 'last login', 'actions']"
                :options="tableOptions">
                <template slot="roles" slot-scope="props">
                    <ul>
                        <li v-for="role in props.row.roles">{{ roleNames[role] }}</li>
                    </ul>
                </template>
                <template slot="status" slot-scope="props">
                    {{ props.row.status ? 'active' : 'inactive' }}
                </template>
                <template slot="actions" slot-scope="props">
                    <a href="#" class="action" title="Edit" @click.prevent="update(props.row)"><i class="fa fa-pencil-square-o"></i></a>
                    <a href="#" class="action" title="Delete" @click.prevent="del(props.row)"><i class="fa fa-trash-o"></i></a>
                </template>
            </v-server-table>
        </article>
        <div class="modal fade" id="formModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" v-if="this.model.id">Edit user "{{ this.model.username }}"</h4>
                        <h4 class="modal-title" v-if="!this.model.id">Add a new user</h4>
                    </div>
                    <div class="modal-body" @keyup.enter="submitForm">
                        <div role="alert" class="alert alert-dismissible alert-danger" v-if="this.error">
                            <button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">×</span></button>
                            <span class="sr-only">Error</span>
                            Something went wrong.
                        </div>
                        <vue-form-generator :schema="schema" :model="model" :options="formOptions" ref="form"></vue-form-generator>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-warning" @click="reset()">Reset</button>
                        <button type="button" class="btn btn-success" @click="submitForm()">Save changes</button>
                    </div>
                </div>
            </div>
            <div class="loading-overlay" v-if="this.openRequest">
                <div class="spinner">
                </div>
            </div>
        </div>
        <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Delete user "{{ this.model.username }}"</h4>
                    </div>
                    <div class="modal-body" @keyup.enter="submitDelete">
                        <div role="alert" class="alert alert-dismissible alert-danger" v-if="this.error">
                            <button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">×</span></button>
                            <span class="sr-only">Error</span>
                            Something went wrong.
                        </div>
                        Are you sure you want to delete user "{{this.model.username}}"?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" @click="submitDelete()">Delete</button>
                    </div>
                </div>
            </div>
            <div class="loading-overlay" v-if="this.openRequest">
                <div class="spinner">
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    window.axios = require('axios')
    var $ = require('jquery')
    require('bootstrap-sass')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import VueTables from 'vue-tables-2'

    Vue.use(VueFormGenerator)
    Vue.use(VueTables.ServerTable)

    export default {
        data() {
            return {
                roleNames: {
                    'ROLE_USER': 'User',
                    'ROLE_VIEW_INTERNAL': 'View internal',
                    'ROLE_EDITOR': 'Editor',
                    'ROLE_ADMIN': 'Admin',
                    'ROLE_SUPER_ADMIN': 'Super admin'
                },
                tableOptions: {
                    'filterable': false,
                    'orderBy': {
                        'column': 'username'
                    },
                    'perPage': 25,
                    'perPageValues': [25, 50, 100],
                    'sortable': ['username', 'email', 'full name', 'status', 'created', 'modified', 'last login']
                },
                formOptions: {
                    validationErrorClass: "has-error",
                    validationSuccessClass: "success"
                },
                showEditModal: false,
                error: false,
                model: {},
                resetModel: {},
                defaultModel: {
                    status: true
                },
                schema: {
                    fields: {
                        username: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Username',
                            model: 'username',
                            maxlength: 180,
                            required: true,
                            validator: VueFormGenerator.validators.string
                        },
                        email: {
                            type: 'input',
                            inputType: 'email',
                            label: 'Email',
                            model: 'email',
                            maxlength: 180,
                            required: true,
                            validator: VueFormGenerator.validators.email
                        },
                        fullName: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Full name',
                            model: 'full name',
                            maxlength: 255,
                            required: true,
                            validator: VueFormGenerator.validators.string
                        },
                        roles: {
                            type: 'checklist',
                            label: 'Roles',
                            model: 'roles',
                            listBox: true,
                            values: () => {
                                let values = []
                                for (let [key, value] of Object.entries(this.roleNames)) {
                                    values.push({ value: key, name: value})
                                }
                                return values
                            }
                        },
                        status: {
                            type: 'checkbox',
                            label: 'Status (active / inactive)',
                            model: 'status'
                        }
                    }
                },
                openRequest: false
            }
        },
        methods: {
            create() {
                this.error = false
                this.model = Object.assign({}, this.defaultModel)
                this.resetModel = Object.assign({}, this.defaultModel)
                $('#formModal').modal({backdrop: 'static'})
            },
            update(user) {
                this.error = false
                this.model = Object.assign({}, user)
                this.resetModel = Object.assign({}, user)
                $('#formModal').modal({backdrop: 'static'})
            },
            reset() {
                this.model = Object.assign({}, this.resetModel)
            },
            submitForm() {
                this.$refs.form.validate()
                if (this.$refs.form.errors.length == 0) {
                    this.openRequest = true
                    if (this.model.id === undefined) {
                        axios.post('/admin/users', this.model)
                            .then( (response) => {
                                this.$refs.table.refresh()
                                $('#formModal').modal('hide')
                            })
                            .catch( (error) => {
                                this.error = true
                                console.log(error)
                            })
                            .finally( () => {
                                this.openRequest = false
                            })
                    }
                    else {
                        axios.put('/admin/users/' + this.model.id, this.model)
                            .then( (response) => {
                                this.$refs.table.refresh()
                                $('#formModal').modal('hide')
                            })
                            .catch( (error) => {
                                this.error = true
                                console.log(error)
                            })
                            .finally( () => {
                                this.openRequest = false
                            })
                        }
                }
            },
            del(user) {
                this.model = Object.assign({}, user)
                $('#confirmModal').modal({backdrop: 'static'})
            },
            submitDelete() {
                this.openRequest = true
                axios.delete('/admin/users/' + this.model.id)
                    .then( (response) => {
                        this.$refs.table.refresh()
                        $('#confirmModal').modal('hide')
                    })
                    .catch( (error) => {
                        this.error = true
                        console.log(error)
                    })
                    .finally( () => {
                        this.openRequest = false
                    })
            }
        }
    }
</script>
