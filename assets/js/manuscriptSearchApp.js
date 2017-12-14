window.axios = require('axios')

import Vue from 'vue'
import VueTables from 'vue-tables-2'
import VueFormGenerator from 'vue-form-generator'

import fieldAutocomplete from './components/formfields/fieldAutocomplete'

Vue.component('fieldAutocomplete', fieldAutocomplete)

Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

let manuscriptSearchApp = new Vue({
    el: '#manuscriptSearchApp',
    delimiters: ['${', '}'],
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
                        type: 'input',
                        inputType: 'text',
                        label: 'Test',
                        model: 'test'
                    }
                ]
            },
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true
            },
            options: {
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
            let removeSorting = false
            let filters = JSON.parse(JSON.stringify(this.model))
            for (let filter in filters) {
                if(filters[filter] !== undefined && filters[filter] !== '') {
                    removeSorting = true
                }
            }
            if (removeSorting) {
                if (this.$refs.resultTable.orderBy.column !== undefined && this.$refs.resultTable.orderBy.ascending !== undefined) {
                    this.oldOrder = {
                        'column': this.$refs.resultTable.orderBy.column,
                        'ascending': this.$refs.resultTable.orderBy.ascending
                    }
                }
                this.$refs.resultTable.setOrder()
            }
            else {
                if (this.oldOrder.column !== undefined && this.oldOrder.ascending !== undefined) {
                    this.$refs.resultTable.orderBy.column = this.oldOrder.column;
                    this.$refs.resultTable.orderBy.ascending = this.oldOrder.ascending;
                }
            }
            VueTables.Event.$emit('vue-tables.filter::filters', this.model)
        }
    }
})
