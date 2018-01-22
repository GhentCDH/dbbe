<template>
    <div>
        <aside class="col-sm-3">
            <div class="bg-tertiary">
                <div class="padding-default">
                    <div class="form-group" v-if="Object.keys(model).length !== 0">
                        <button class="btn btn-block" @click="resetAllFilters">Reset all filters</button>
                    </div>
                    <vue-form-generator :schema="schema" :model="model" :options="formOptions" @model-updated="modelUpdated" @validated="onValidated"></vue-form-generator>
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
                <a slot="name" slot-scope="props" :href="'/manuscripts/' + props.row.id">
                    {{ props.row.name }}
                </a>
                <template slot="date" slot-scope="props" v-if="props.row.date_floor_year && props.row.date_ceiling_year">
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
                </template>
                <template slot="content" slot-scope="props" v-if="props.row.content">
                    <template v-for="displayContent in filterDisplayContent(props.row.content)">
                        <ul v-if="displayContent.length > 1">
                            <li v-for="content in displayContent">{{ content.name }}</li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name}}
                        </template>
                    </template>
                </template>
            </v-server-table>
            <div class="loading-overlay" v-if="this.openTableRequests">
                <div class="spinner">
                </div>
            </div>
        </article>
    </div>
</template>
<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import VueMultiselect from 'vue-multiselect'
    import VueTables from 'vue-tables-2'

    import fieldMultiselectClear from '../components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)
    Vue.use(VueTables.ServerTable)

    Vue.component('multiselect', VueMultiselect)
    Vue.component('fieldMultiselectClear', fieldMultiselectClear)

    var YEAR_MIN = 1
    var YEAR_MAX = (new Date()).getFullYear()

    export default {
        data() {
            return {
                model: {},
                schema: {
                    fields: {
                        city: this.createMultiSelect('City'),
                        library: this.createMultiSelect('Library', {dependency: 'city'}),
                        fund: this.createMultiSelect('Collection', {dependency: 'library', model: 'fund'}),
                        shelf: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Shelf Number',
                            model: 'shelf'
                        },
                        year_from: {
                            type: 'input',
                            inputType: 'number',
                            label: 'Year from',
                            model: 'year_from',
                            validator: VueFormGenerator.validators.number
                        },
                        year_to: {
                            type: 'input',
                            inputType: 'number',
                            label: 'Year to',
                            model: 'year_to',
                            validator: VueFormGenerator.validators.number
                        },
                        content: this.createMultiSelect('Content'),
                        patron: this.createMultiSelect('Patron'),
                        scribe: this.createMultiSelect('Scribe'),
                        origin: this.createMultiSelect('Origin')
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
                    customFilters: ['filters'],
                    requestFunction: function (data) {
                        if (this.$parent.openTableRequests > 0) {
                            this.$parent.tableCancel('Operation canceled by newer request')
                        }
                        this.$parent.openTableRequests++
                        return axios.get(this.url, {
                            params: data,
                            cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
                        })
                            .then( (response) => {
                                this.$parent.openTableRequests--
                                return response
                            })
                            .catch(function (error) {
                                this.$parent.openTableRequests--
                                if (axios.isCancel(error)) {
                                    // Return the current data if the request is cancelled
                                    return {
                                        data : {
                                            data: this.data,
                                            count: this.count
                                        }
                                    }
                                }
                                this.dispatch('error', error)
                            }.bind(this))
                    }
                },
                oldOrder: {},
                openFilterRequests: 0,
                filterCancel: null,
                openTableRequests: 0,
                tableCancel: null,
                lastChangedField: '',
                inputCancel: null
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.setFilters()
            })
        },
        methods: {
            filterDisplayContent(contentList) {
                // extra dimension is needed to declare variable in template using v-for
                let result = [[]]
                for (let contentItem of contentList) {
                    if (contentItem.display) {
                        result[0].push(contentItem)
                    }
                }
                return result
            },
            createMultiSelect(label, extra) {
                let result = {
                    type: 'multiselectClear',
                    label: label,
                    placeholder: 'Loading',
                    model: label.toLowerCase(),
                    // Values will be loaded using ajax request
                    values: [],
                    selectOptions: {
                        customLabel: ({id, name}) => {
                            return name
                        },
                        showLabels: false,
                        loading: true
                    },
                    // Will be enabled when list of scribes is loaded
                    disabled: true
                }
                if (extra !== undefined) {
                    for (let key of Object.keys(extra)) {
                        result[key] = extra[key]
                    }
                }
                return result
            },
            cleanFilterValues() {
                let result = {
                    // default values for date
                    'date': [
                        1,
                        2000
                    ]
                }
                if (this.model !== undefined) {
                    for (let fieldName of Object.keys(this.model)) {
                        if (this.model[fieldName] === null) {
                            continue
                        }
                        if (this.schema.fields[fieldName].type === 'multiselectClear') {
                            result[fieldName] = this.model[fieldName]['id']
                        }
                        else if (fieldName === 'year_from') {
                            result['date'][0] = this.model[fieldName]
                        }
                        else if (fieldName === 'year_to') {
                            result['date'][1] = this.model[fieldName]
                        }
                        else {
                            result[fieldName] = this.model[fieldName]
                        }
                    }
                }
                return result
            },
            setFilters(filterValues) {
                if (this.openFilterRequests > 0) {
                    this.filterCancel('Operation canceled by newer request')
                }
                for (let fieldName of Object.keys(this.schema.fields)) {
                    if (this.schema.fields[fieldName].type == 'multiselectClear') {
                        if (
                            this.model[fieldName] && this.schema.fields[fieldName].dependency
                            && (this.model[this.schema.fields[fieldName].dependency] === undefined
                            || this.model[this.schema.fields[fieldName].dependency] === null)
                        ) {
                            this.model[fieldName] = null
                        }
                        this.disableField(fieldName)
                    }
                }
                this.openFilterRequests++
                axios.post('/manuscripts/filtervalues', filterValues, {
                    cancelToken: new axios.CancelToken((c) => {this.filterCancel = c})
                })
                    .then( (response) => {
                        this.openFilterRequests--
                        for (let fieldName of Object.keys(this.schema.fields)) {
                            if (this.schema.fields[fieldName].type == 'multiselectClear') {
                                this.enableField(fieldName, response.data[fieldName] === undefined ? [] : response.data[fieldName].sort(this.sortByName))
                            }
                        }
                    })
                    .catch( (error) => {
                        this.openFilterRequests--
                        if (!axios.isCancel(error)) {
                            console.log(error)
                        }
                    })
            },
            modelUpdated(value, fieldName) {
                this.lastChangedField = fieldName
            },
            onValidated(isValid, errors) {
                // do nothin but cancelling requests if invalid
                if (!isValid) {
                    if (this.inputCancel !== null) {
                        window.clearTimeout(this.inputCancel)
                        this.inputCancel = null
                    }
                    return
                }

                // set year min and max values
                if (this.model.year_from !== undefined && this.model.year_from !== null) {
                    this.schema.fields.year_from.max = Math.min(YEAR_MAX, this.model.year_to)
                }
                if (this.model.year_to !== undefined && this.model.year_to !== null) {
                    this.schema.fields.year_to.min = Math.max(YEAR_MIN, this.model.year_from)
                }

                // Cancel timeouts caused by input requests not long ago
                if (this.inputCancel !== null) {
                    window.clearTimeout(this.inputCancel)
                    this.inputCancel = null
                }

                // Send requests to update filters and result table
                // Add a delay to requests originated from input field changes to limit the number of requests
                let timeoutValue = 0
                if (this.lastChangedField !== '' && this.schema.fields[this.lastChangedField].type === 'input') {
                    timeoutValue = 1000
                }
                this.inputCancel = window.setTimeout(() => {
                    this.inputCancel = null
                    let filterValues = this.cleanFilterValues()
                    this.setFilters(filterValues)
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues)
                }, timeoutValue)
            },
            disableField(fieldName) {
                this.schema.fields[fieldName].disabled = true
                this.schema.fields[fieldName].placeholder = 'Loading'
                this.schema.fields[fieldName].selectOptions.loading = true
                this.schema.fields[fieldName].values = []
            },
            enableField(fieldName, values) {
                let label = this.schema.fields[fieldName].label.toLowerCase()
                this.schema.fields[fieldName].selectOptions.loading = false
                this.schema.fields[fieldName].placeholder = (['origin'].indexOf(label) < 0 ? 'Select a ' : 'Select an ') + label
                // Handle dependencies
                if (this.schema.fields[fieldName].dependency !== undefined) {
                    let dependency = this.schema.fields[fieldName].dependency
                    if (this.model[dependency] === undefined || this.model[dependency] === null) {
                        this.schema.fields[fieldName].placeholder = 'Please select a ' + dependency + ' first'
                        return
                    }
                }
                // No results
                if (values.length === 0) {
                    if (this.model[fieldName] !== undefined && this.model[fieldName] !== null) {
                        this.schema.fields[fieldName].disabled = false
                        return
                    }
                    return
                }
                // Default
                this.schema.fields[fieldName].disabled = false
                this.schema.fields[fieldName].values = values
            },
            sortByName(a, b) {
                // Move special filter values to the top
                if (a.id === -1) {
                    return -1
                }
                if (b.id === -1) {
                    return 1
                }
                if (a.name < b.name) {
                    return -1
                }
                if (a.name > b.name) {
                    return 1
                }
                return 0
            },
            resetAllFilters() {
                this.model = {}
                this.onValidated(true)
            }
        }
    }
</script>
