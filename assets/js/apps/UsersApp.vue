<template>
    <div>
        <article class="col-xs-12">
            <h2>Users</h2>
            <btn
                type="success"
                @click="create">
                <i class="fa fa-user-plus" /> Add a new user
            </btn>
            <p>Emails and full names will be automatically completed at the first login.</p>
            <v-server-table
                url="/admin/users"
                ref="table"
                :columns="['username', 'email', 'full name', 'roles', 'status', 'created', 'modified', 'last login', 'actions']"
                :options="tableOptions"
                @loaded="tableLoaded">
                <template
                    slot="roles"
                    slot-scope="props">
                    <ul>
                        <li
                            v-for="(role, index) in props.row.roles"
                            :key="index">
                            {{ roleNames[role] }}
                        </li>
                    </ul>
                </template>
                <template
                    slot="status"
                    slot-scope="props">
                    {{ props.row.status ? 'active' : 'inactive' }}
                </template>
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        href="#"
                        class="action"
                        title="Edit"
                        @click.prevent="update(props.row)">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                </template>
            </v-server-table>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <modal
            v-model="formModal"
            auto-focus>
            <alert
                v-for="(item, index) in alerts"
                :key="item.key"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
            <p>Emails and full names will be automatically completed at the first login.</p>
            <vue-form-generator
                :schema="schema"
                :model="model"
                :options="formOptions"
                ref="form" />
            <div slot="header">
                <h4
                    class="modal-title"
                    v-if="model.id">
                    Edit user "{{ model.username }}"
                </h4>
                <h4
                    class="modal-title"
                    v-if="!model.id">
                    Add a new user
                </h4>
            </div>
            <div slot="footer">
                <btn @click="formModal=false">Cancel</btn>
                <btn
                    type="warning"
                    @click="reset()">
                    Reset
                </btn>
                <btn
                    type="success"
                    @click="submitForm()">
                    Save changes
                </btn>
            </div>
        </modal>
    </div>
</template>
<script>
window.axios = require('axios')

import * as uiv from 'uiv'
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueTables from 'vue-tables-2'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

export default {
    data() {
        return {
            roleNames: {
                'ROLE_USER': 'User',
                'ROLE_VIEW_INTERNAL': 'View internal',
                'ROLE_EDITOR_VIEW': 'Editor (read only)',
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
            alerts: [],
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
            formModal: false,
            openRequests: 0
        }
    },
    methods: {
        create() {
            this.model = Object.assign({}, this.defaultModel)
            this.resetModel = Object.assign({}, this.defaultModel)
            this.schema.fields.username.disabled = false
            this.formModal = true
        },
        update(user) {
            this.model = Object.assign({}, user)
            this.resetModel = Object.assign({}, user)
            this.schema.fields.username.disabled = true
            this.formModal = true
        },
        reset() {
            this.model = Object.assign({}, this.resetModel)
        },
        submitForm() {
            this.$refs.form.validate()
            if (this.$refs.form.errors.length == 0) {
                this.openRequests++
                this.formModal = false
                // create new user
                if (this.model.id === undefined) {
                    axios.post('/admin/users', this.model)
                        .then( (response) => {
                            this.$refs.table.refresh()
                        })
                        .catch( (error) => {
                            this.formModal = true
                            this.openRequests--
                            this.alerts.push({type: 'error', message: 'Something whent wrong while saving the new user.'})
                            console.log(error)
                        })
                }
                // update existing user
                else {
                    axios.put('/admin/users/' + this.model.id, this.model)
                        .then( (response) => {
                            this.$refs.table.refresh()
                        })
                        .catch( (error) => {
                            this.formModal = true
                            this.openRequests--
                            this.alerts.push({type: 'error', message: 'Something whent wrong while saving the updated user.'})
                            console.log(error)
                        })
                }
            }
        },
        tableLoaded() {
            if (this.openRequests > 0) {
                this.openRequests--
            }
        }
    }
}
</script>
