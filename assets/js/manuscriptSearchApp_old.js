window.axios = require('axios');

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
            form: {
                nameField: {
                    model: 'name',
                    name: 'name',
                    label: 'Name',
                    placeholder: 'Manuscript Name',
                    url: '/manuscripts/suggest_api/name/',
                    update: 'data-update',
                    event: 'apply-filters'
                }
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
            }
        }
    },
    methods: {
        dataUpdate(model, value){
            this.model['model'] = value
            console.log(this.model)
        },
        applyFilters() {
            console.log(this.model)
            VueTables.Event.$emit('vue-tables.filter::filters', 'test')
        }
    }
})
