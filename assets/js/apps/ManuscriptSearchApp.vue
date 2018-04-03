<template>
    <div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <div
                    v-if="Object.keys(model).length !== 0"
                    class="form-group">
                    <button
                        class="btn btn-block"
                        @click="resetAllFilters">
                        Reset all filters
                    </button>
                </div>
                <vue-form-generator
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated" />
            </div>
        </aside>
        <article class="col-sm-9">
            <h2>Search Manuscripts</h2>
            <v-server-table
                ref="resultTable"
                :url="manuscriptsSearchApiUrl"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData">
                <a
                    slot="name"
                    slot-scope="props"
                    :href="showManuscriptUrl.replace('manuscript_id', props.row.id)">
                    {{ props.row.name }}
                </a>
                <template
                    v-if="props.row.date_floor_year && props.row.date_ceiling_year"
                    slot="date"
                    slot-scope="props">
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
                </template>
                <template
                    v-if="props.row.content"
                    slot="content"
                    slot-scope="props">
                    <template v-for="(displayContent, index) in [props.row.content.filter((content) => content['display'])]">
                        <ul
                            v-if="displayContent.length > 1"
                            :key="index">
                            <li
                                v-for="(content, contentIndex) in displayContent"
                                :key="contentIndex">
                                {{ content.name }}
                            </li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name }}
                        </template>
                    </template>
                </template>
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="editManuscriptUrl.replace('manuscript_id', props.row.id)"
                        class="action"
                        title="Edit">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)">
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
            </v-server-table>
        </article>
        <modal
            v-model="delModal"
            auto-focus>
            <alert
                v-for="(item, index) in alerts"
                :key="item.key"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
            Are you sure you want to delete manuscript "{{ delManuscript.name }}"?
            <div slot="header">
                <h4 class="modal-title">Delete manuscript "{{ delManuscript.name }}"</h4>
            </div>
            <div slot="footer">
                <btn @click="delModal=false">
                    Cancel
                </btn>
                <btn
                    type="danger"
                    @click="submitDelete()">
                    Delete
                </btn>
            </div>
        </modal>
        <div
            v-if="openRequests"
            class="loading-overlay">
            <div class="spinner" />
        </div>
    </div>
</template>
<script>
window.axios = require('axios')

import * as uiv from 'uiv'
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import VueTables from 'vue-tables-2'

import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)

var YEAR_MIN = 1
var YEAR_MAX = (new Date()).getFullYear()

export default {
    props: {
        isEditor: {
            type: Boolean,
            default: false,
        },
        manuscriptsSearchApiUrl: {
            type: String,
            default: '',
        },
        showManuscriptUrl: {
            type: String,
            default: '',
        },
        editManuscriptUrl: {
            type: String,
            default: '',
        },
        delManuscriptUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            model: {},
            schema: {
                fields: {
                    city: this.createMultiSelect('City', {}, {trackBy: 'id'}),
                    library: this.createMultiSelect('Library', {dependency: 'city'}, {trackBy: 'id'}),
                    collection: this.createMultiSelect('Collection', {dependency: 'library', model: 'collection'}, {trackBy: 'id'}),
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
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    },
                    content: this.createMultiSelect('Content', {}, {trackBy: 'id'}),
                    patron: this.createMultiSelect('Patron', {}, {trackBy: 'id'}),
                    scribe: this.createMultiSelect('Scribe', {}, {trackBy: 'id'}),
                    origin: this.createMultiSelect('Origin', {}, {trackBy: 'id'})
                }
            },
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
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
                    if (this.$parent.openRequests > 0) {
                        this.$parent.tableCancel('Operation canceled by newer request')
                    }
                    this.$parent.openRequests++
                    return axios.get(this.url, {
                        params: data,
                        cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
                    })
                        .then( (response) => {
                            this.$emit('data', response.data)
                            return response
                        })
                        .catch(function (error) {
                            this.$parent.openRequests--
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
            openRequests: 0,
            filterCancel: null,
            tableCancel: null,
            // used to set timeout on free input fields
            lastChangedField: '',
            // used to only send requests after timeout when inputting free input fields
            inputCancel: null,
            // Remove requesting the same data that is already displayed
            oldFilterValues: this.constructFilterValues(),
            delManuscript: {
                id: 0,
                name: ''
            },
            delModal: false,
            alerts: []
        }
    },
    computed: {
        tableColumns() {
            let columns = ['name', 'date', 'content']
            if (this.isEditor) {
                columns.push('actions')
            }
            return columns
        },
    },
    watch: {
        'model.city'() {
            if (this.model.city == null) {
                delete this.model.library
                delete this.model.collection
                this.disableField(this.schema.fields.library)
                this.disableField(this.schema.fields.collection)
            }
        },
        'model.library'() {
            if (this.model.library == null) {
                delete this.model.collection
                this.disableField(this.schema.fields.collection)
            }
        },
    },
    methods: {
        createMultiSelect(label, extra, extraSelectOptions) {
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
            if (extra != null) {
                for (let key of Object.keys(extra)) {
                    result[key] = extra[key]
                }
            }
            if (extraSelectOptions != null) {
                for (let key of Object.keys(extraSelectOptions)) {
                    result['selectOptions'][key] = extraSelectOptions[key]
                }
            }
            return result
        },
        constructFilterValues() {
            let result = {
                // default values for date
                'date': [
                    1,
                    2000
                ]
            }
            if (this.model != null) {
                for (let fieldName of Object.keys(this.model)) {
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
        // setFilters(filterValues) {
        //     if (this.openFilterRequests > 0) {
        //         this.filterCancel('Operation canceled by newer request')
        //     }
        //     this.openFilterRequests++
        //     axios.post(this.manuscriptsFiltervaluesUrl, filterValues, {
        //         cancelToken: new axios.CancelToken((c) => {this.filterCancel = c})
        //     })
        //         .then( (response) => {
        //             this.openFilterRequests--
        //             for (let fieldName of Object.keys(this.schema.fields)) {
        //                 if (this.schema.fields[fieldName].type == 'multiselectClear') {
        //                     this.enableField(this.schema.fields[fieldName], response.data[fieldName] == null ? [] : response.data[fieldName].sort(this.sortByName))
        //                 }
        //             }
        //         })
        //         .catch( (error) => {
        //             this.openFilterRequests--
        //             if (!axios.isCancel(error)) {
        //                 console.log(error)
        //             }
        //         })
        // },
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

            if (this.model != null) {
                for (let fieldName of Object.keys(this.model)) {
                    if (
                        this.model[fieldName] === null ||
                        this.model[fieldName] === '' ||
                        ((['year_from', 'year_to'].indexOf(fieldName) > -1) && isNaN(this.model[fieldName]))
                    ) {
                        delete this.model[fieldName];
                    }
                }
            }

            // set year min and max values
            if (this.model.year_from != null) {
                this.schema.fields.year_to.min = Math.max(YEAR_MIN, this.model.year_from)
            }
            else {
                this.schema.fields.year_to.min = YEAR_MIN
            }
            if (this.model.year_to != null) {
                this.schema.fields.year_from.max = Math.min(YEAR_MAX, this.model.year_to)
            }
            else {
                this.schema.fields.year_from.max = YEAR_MAX
            }

            // Cancel timeouts caused by input requests not long ago
            if (this.inputCancel != null) {
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
                let filterValues = this.constructFilterValues()
                // only send request if the filters have changed
                // filters are always in the same order, so we can compare serialization
                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues
                    // this.setFilters(filterValues)
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues)
                }
            }, timeoutValue)
        },
        disableField(field) {
            field.disabled = true
            field.placeholder = 'Loading'
            field.selectOptions.loading = true
            field.values = []
        },
        enableField(field, values) {
            let label = field.label.toLowerCase()
            field.selectOptions.loading = false
            field.placeholder = (['origin'].indexOf(label) < 0 ? 'Select a ' : 'Select an ') + label
            // Handle dependencies
            if (field.dependency != null) {
                let dependency = field.dependency
                if (this.model[dependency] == null) {
                    field.placeholder = 'Please select a ' + dependency + ' first'
                    return
                }
            }
            // No results
            if (values.length === 0) {
                if (this.model[field.model] != null) {
                    field.disabled = false
                    return
                }
                return
            }
            // Default
            field.disabled = false
            field.values = values
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
        },
        del(row) {
            this.delManuscript = row
            this.delModal = true
        },
        submitDelete() {
            this.openRequests++
            this.delModal = false
            axios.delete(this.delManuscriptUrl.replace('manuscript_id', this.delManuscript.id))
                .then( (response) => {
                    this.$refs.table.refresh()
                    this.openRequests--
                })
                .catch( (error) => {
                    this.delModal = true
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the manuscript.'})
                    console.log(error)
                })
        },
        onData(data) {
            for (let fieldName of Object.keys(this.schema.fields)) {
                if (this.schema.fields[fieldName].type == 'multiselectClear') {
                    let values = data.aggregation[fieldName] == null ? [] : data.aggregation[fieldName].sort(this.sortByName)
                    // Make it possible to filter on all manuscripts without collection
                    if (fieldName == 'collection' && values.length > 0) {
                        values.push({
                            id: -1,
                            name: 'No collection',
                        })
                    }
                    this.enableField(this.schema.fields[fieldName], values)
                }
            }
            this.openRequests--
        }
    }
}
</script>
