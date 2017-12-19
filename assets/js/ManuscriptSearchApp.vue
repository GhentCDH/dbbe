<template>
    <div>
        <aside class="col-sm-3">
            <div class="bg-tertiary">
                <div class="padding-default">
                    <vue-form-generator :schema="schema" :model="model" :options="formOptions" @model-updated="updateFilters"></vue-form-generator>
                </div>
            </div>
        </aside>
        <article class="col-sm-9">
            <h2>Search Manuscripts</h2>
            <v-server-table
                ref="resultTable"
                url="/manuscripts/search_api"
                :columns="['name', 'date', 'content']"
                :options="tableOptions">
                <a slot="name" slot-scope="props" :href="'/manuscrips/' + props.row.id">{{ props.row.name}}</a>
                <template slot="content" slot-scope="props">
                    <ul v-if="props.row.content.includes('|')">
                        <li v-for="content in props.row.content.split('|')">{{ content }}</li>
                    </ul>
                    <template v-else>
                        {{ props.row.content}}
                    </template>
                </template>
                <a slot="edit" slot-scope="props" class="fa fa-edit" :href="'/manuscrips/' + props.row.id + '/edit'"></a>
            </v-server-table>
        </article>
    </div>
</template>
<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueTables from 'vue-tables-2'
    import VueFormGenerator from 'vue-form-generator'

    import fieldAutocomplete from './components/formfields/fieldAutocomplete'

    Vue.component('fieldAutocomplete', fieldAutocomplete)

    Vue.use(VueFormGenerator)
    Vue.use(VueTables.ServerTable)

    export default {
        data() {
            return {
                model: {},
                schema: {
                    fields: [
                        {
                            type: 'autocomplete',
                            label: 'Name',
                            placeholder: 'Manuscript Name',
                            model: 'name',
                            url: '/manuscripts/suggest_api/name/'
                        },
                        {
                            type: 'autocomplete',
                            label: 'Content',
                            placeholder: 'Manuscript Content',
                            model: 'content',
                            url: '/manuscripts/suggest_api/content/'
                        }
                    ]
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true
                },
                tableOptions: {
                    'filterable': false,
                    'orderBy': {
                        'column': 'name'
                    },
                    'perPage': 25,
                    'perPageValues': [25, 50, 100],
                    'sortable': ['name', 'date'],
                    customFilters: ['filters']
                },
                oldOrder: {}
            }
        },
        methods: {
            updateFilters() {
                let filters = JSON.parse(JSON.stringify(this.model))
                for (let key of Object.keys(filters)) {
                    if(filters[key] === undefined || filters[key] === '') {
                        delete filters[key]
                    }
                }
                // Save old table sorting options and unset table sorting
                if (Object.keys(filters).length > 0) {
                    if (this.$refs.resultTable.orderBy.column !== undefined && this.$refs.resultTable.orderBy.ascending !== undefined) {
                        this.oldOrder = {
                            'column': this.$refs.resultTable.orderBy.column,
                            'ascending': this.$refs.resultTable.orderBy.ascending
                        }
                    }
                    this.$refs.resultTable.orderBy.column = ''
                    this.$refs.resultTable.orderBy.ascending = ''
                }
                else {
                    if (this.oldOrder.column !== undefined && this.oldOrder.ascending !== undefined) {
                        this.$refs.resultTable.orderBy.column = this.oldOrder.column;
                        this.$refs.resultTable.orderBy.ascending = this.oldOrder.ascending;
                    }
                }
                VueTables.Event.$emit('vue-tables.filter::filters', filters)
            }
        }
    }
</script>
