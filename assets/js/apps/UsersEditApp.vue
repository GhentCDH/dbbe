<template>
    <div>
        <article class="col-xs-12">
            <h2>Users</h2>
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <div class="pbottom-default">
                <btn
                    type="success"
                    @click="create"
                >
                    <i class="fa fa-user-plus" /> Add a new user
                </btn>
            </div>
            <v-server-table
                ref="table"
                :url="urls['users_get']"
                :columns="['username', 'roles', 'created', 'modified', 'last login', 'actions']"
                :options="tableOptions"
                @loading="tableLoading"
                @loaded="tableLoaded"
            >
                <template
                    slot="roles"
                    slot-scope="props"
                >
                    <ul>
                        <li
                            v-for="(role, index) in props.row.roles"
                            :key="index"
                        >
                            {{ roleNames[role] }}
                        </li>
                    </ul>
                </template>
                <template
                    slot="actions"
                    slot-scope="props"
                >
                    <a
                        href="#"
                        class="action"
                        title="Edit"
                        @click.prevent="update(props.row)"
                    >
                        <i class="fa fa-pencil-square-o" />
                    </a>
                </template>
            </v-server-table>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </article>
        <modal
            v-model="formModal"
            auto-focus
        >
            <alerts
                :alerts="editAlerts"
                @dismiss="editAlerts.splice($event, 1)"
            />
            <vue-form-generator
                ref="form"
                :schema="schema"
                :model="model"
                :options="formOptions"
            />
            <div slot="header">
                <h4
                    v-if="model.id"
                    class="modal-title"
                >
                    Edit user "{{ model.username }}"
                </h4>
                <h4
                    v-if="!model.id"
                    class="modal-title"
                >
                    Add a new user
                </h4>
            </div>
            <div slot="footer">
                <btn @click="formModal=false">Cancel</btn>
                <btn
                    type="warning"
                    @click="reset()"
                >
                    Reset
                </btn>
                <btn
                    type="success"
                    @click="submitForm()"
                >
                    Save changes
                </btn>
            </div>
        </modal>
    </div>
</template>
<script>
window.axios = require('axios');

import * as uiv from 'uiv'
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueTables from 'vue-tables-2'

import Alerts from '../Components/Alerts'

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(VueTables.ServerTable);

Vue.component('alerts', Alerts);

export default {
    props: {
        initUrls: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            urls: JSON.parse(this.initUrls),
            roleNames: {
                'ROLE_VIEW_INTERNAL': 'View internal',
                'ROLE_EDITOR_VIEW': 'Editor (read only)',
                'ROLE_EDITOR': 'Editor',
                'ROLE_JULIE': 'Julie plugin',
                'ROLE_ADMIN': 'Admin',
                'ROLE_SUPER_ADMIN': 'Super admin'
            },
            tableOptions: {
                headings: {
                    username: 'Email',
                },
                'filterable': false,
                'orderBy': {
                    'column': 'username'
                },
                'perPage': 25,
                'perPageValues': [25, 50, 100],
                'sortable': ['username', 'created', 'modified', 'last login']
            },
            formOptions: {
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            showEditModal: false,
            alerts: [],
            editAlerts: [],
            model: {},
            resetModel: {},
            defaultModel: {
                roles: [],
            },
            schema: {
                fields: {
                    username: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Email',
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
                            let values = [];
                            for (let [key, value] of Object.entries(this.roleNames)) {
                                values.push({ value: key, name: value})
                            }
                            return values
                        }
                    },
                },
            },
            formModal: false,
            openRequests: 0,
        }
    },
    methods: {
        create() {
            this.model = Object.assign({}, this.defaultModel);
            this.resetModel = Object.assign({}, this.defaultModel);
            this.schema.fields.username.disabled = false;
            this.formModal = true
        },
        update(user) {
            this.model = Object.assign({}, user);
            this.resetModel = Object.assign({}, user);
            this.schema.fields.username.disabled = true;
            this.formModal = true
        },
        reset() {
            this.model = Object.assign({}, this.resetModel)
        },
        submitForm() {
            this.$refs.form.validate();
            if (this.$refs.form.errors.length == 0) {
                this.openRequests++;
                this.formModal = false;
                // create new user
                if (this.model.id === undefined) {
                    axios.post(this.urls['user_post'], this.model)
                        .then( (response) => {
                            this.$refs.table.refresh();
                            this.openRequests--;
                            this.alerts.push({type: 'success', message: 'User ' + response.data.username + ' added successfully.'})
                        })
                        .catch( (error) => {
                            this.formModal = true;
                            this.openRequests--;
                            this.editAlerts.push({type: 'error', message: 'Something went wrong while saving the new user.', login: this.isLoginError(error)});
                            console.log(error)
                        })
                }
                // update existing user
                else {
                    axios.put(this.urls['user_put'].replace('user_id', this.model.id), this.model)
                        .then( (response) => {
                            this.$refs.table.refresh();
                            this.openRequests--;
                            this.alerts.push({type: 'success', message: 'User ' + response.data.username + ' updated successfully.'})
                        })
                        .catch( (error) => {
                            this.formModal = true;
                            this.openRequests--;
                            this.editAlerts.push({type: 'error', message: 'Something went wrong while saving the updated user.', login: this.isLoginError(error)});
                            console.log(error)
                        })
                }
            }
        },
        tableLoading() {
            this.openRequests++;
        },
        tableLoaded() {
            if (this.openRequests > 0) {
                this.openRequests--
            }
        },
        isLoginError(error) {
            return error.message === 'Network Error'
        },
    }
}
</script>
