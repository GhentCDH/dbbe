<template>
    <div>
        <article class="col-sm-9">
            <h2>Users</h2>
            <v-server-table
                url="/admin/users"
                :columns="['username', 'email', 'full name', 'roles', 'status', 'created', 'modified', 'last login', 'actions']"
                :options="tableOptions">
                <template slot="roles" slot-scope="props">
                    <ul>
                        <li v-for="role in props.row.roles">{{ role }}</li>
                    </ul>
                </template>
                <template slot="status" slot-scope="props">
                    {{ props.row.status ? 'active' : 'inactive' }}
                </template>
                <template slot="actions" slot-scope="props">
                    <a href="#" class="action" title="Edit" @click="edit(props.row. id)"><i class="fa fa-pencil-square-o"></i></a>
                    <a href="#" class="action" title="Delete" @click="del(props.row. id)"><i class="fa fa-trash-o"></i></a>
                </template>
            </v-server-table>
        </article>
    </div>
</template>
<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueTables from 'vue-tables-2'
    Vue.use(VueTables.ServerTable)

    export default {
        data() {
            return {
                tableOptions: {
                    'filterable': false,
                    'orderBy': {
                        'column': 'username'
                    },
                    'perPage': 25,
                    'perPageValues': [25, 50, 100],
                    'sortable': ['username', 'email', 'full name', 'status', 'created', 'modified', 'last login']
                }
            }
        }
    }
</script>
