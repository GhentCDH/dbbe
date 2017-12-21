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
                <a slot="name" slot-scope="props" :href="'/manuscrips/' + props.row.id">
                    {{ formatName(props.row) }}
                </a>
                <template slot="content" slot-scope="props" v-if="props.row.content">
                    <ul v-if="props.row.content.includes('|')">
                        <li v-for="content in props.row.content.split('|')">{{ content }}</li>
                    </ul>
                    <template v-else>
                        {{ props.row.content}}
                    </template>
                </template>
            </v-server-table>
        </article>
    </div>
</template>
<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueTables from 'vue-tables-2'
    import VueMultiselect from 'vue-multiselect'
    import VueFormGenerator from 'vue-form-generator'

    import fieldMultiselectClear from './components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)
    Vue.use(VueTables.ServerTable)

    Vue.component('multiselect', VueMultiselect);
    Vue.component('fieldMultiselectClear', fieldMultiselectClear);

    export default {
        data() {
            return {
                model: {},
                schema: {
                    fields: {
                        city: {
                            type: 'multiselectClear',
                            label: 'City',
                            placeholder: 'Select a city',
                            model: 'city',
                            // Values will be loaded using ajax request
                            values: [],
                            selectOptions: {
                                showLabels: false,
                                loading: true
                            },
                            // Will be enabled when list of cities are loaded
                            disabled: true,
                            onChanged: this.citySelected
                        },
                        library: {
                            type: 'multiselectClear',
                            label: 'Library',
                            placeholder: 'Please select a city first',
                            model: 'library',
                            // Values will be loaded using ajax request
                            values: [],
                            selectOptions: {
                                showLabels: false
                            },
                            // Will be enabled when list of libraries are loaded
                            // after city selection
                            disabled: true
                        }
                    }
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
        mounted () {
            this.$nextTick( () => {
                axios.get('/manuscripts/cities')
                    .then( (response) => {
                        this.$data.schema.fields['city'].disabled = false
                        this.$data.schema.fields['city'].selectOptions.loading = false
                        this.$data.schema.fields['city'].values = Object.keys(response.data).sort()
                    })
                    .catch( (error) => {
                        console.log(error)
                    })
            })
        },
        methods: {
            formatName(row) {
                let result = ''
                result += row.city.toUpperCase()
                if (row.library) {
                    result += ' - ' +  row.library
                }
                if (row.fund) {
                    result += ' - ' +  row.fund
                }
                if (row.shelf) {
                    result += ' ' +  row.shelf
                }
                return result

            },
            updateFilters() {
                let filters = JSON.parse(JSON.stringify(this.model))
                // if city is reset, wait untill library and fund are reset as well
                if (
                    (
                        (filters['library'] !== undefined && filters['library'] !== null)
                        || (filters['fund'] !== undefined && filters['fund'] !== null)
                    )
                    && filters['city'] === null
                ) {
                    return
                }
                // if library is reset, wait untill fund is reset as well
                if (
                    (filters['fund'] !== undefined && filters['fund'] !== null)
                    && filters['library'] === null
                ) {
                    return
                }
                for (let key of Object.keys(filters)) {
                    if(filters[key] === undefined || filters[key] === null || filters[key] === '') {
                        delete filters[key]
                    }
                    // make sure the complete filter is matched
                    else if (['city', 'library', 'fund'].includes(key)) {
                        filters[key + '.keyword'] = filters[key]
                        delete filters[key]
                    }
                }
                VueTables.Event.$emit('vue-tables.filter::filters', filters)
            },
            citySelected(model, newVal, oldVal, field) {
                if (model.city === undefined || model.city === null) {
                    model.library = null
                    this.$data.schema.fields['library'].disabled = true
                    this.$data.schema.fields['library'].placeholder = 'Please select a city first'
                    this.$data.schema.fields['library'].values = []
                    this.updateFilters()
                }
                else {
                    this.$data.schema.fields['library'].selectOptions.loading = true
                    axios.get('/manuscripts/libraries/' + model.city)
                        .then( (response) => {
                            this.$data.schema.fields['library'].disabled = false
                            this.$data.schema.fields['library'].placeholder = 'Select a library'
                            this.$data.schema.fields['library'].selectOptions.loading = false
                            this.$data.schema.fields['library'].values = Object.keys(response.data).sort()
                        })
                        .catch( (error) => {
                            console.log(error)
                        })
                    }
            }
        }
    }
</script>
