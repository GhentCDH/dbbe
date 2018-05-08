<template>
    <div>
        <div class="col-xs-12">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
        </div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <div
                    v-if="JSON.stringify(model) !== JSON.stringify(originalModel)"
                    class="form-group">
                    <button
                        class="btn btn-block"
                        @click="resetAllFilters">
                        Reset all filters
                    </button>
                </div>
                <vue-form-generator
                    ref="form"
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated" />
            </div>
        </aside>
        <article class="col-sm-9">
            <v-server-table
                ref="resultTable"
                :url="occurrencesSearchApiUrl"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData">
                <a
                    slot="incipit"
                    slot-scope="props"
                    :href="showOccurrenceUrl.replace('occurrence_id', props.row.id)"
                    v-html="props.row.incipit" />
                <span
                    slot="manuscript"
                    slot-scope="props"
                    v-html="props.row.manuscript.name" />
                <template
                    slot="text"
                    slot-scope="props">
                    <ul v-if="props.row.text.length > 1">
                        <li
                            v-for="(item, index) in props.row.text"
                            :key="index"
                            v-html="item" />
                    </ul>
                    <span
                        v-else
                        v-html="props.row.text[0]" />
                </template>
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="editOccurrenceUrl.replace('occurrence_id', props.row.id)"
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
            <!-- <div v-if="delDependencies.length !== 0">
                <p>This occurrence has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies"
                        :key="dependency.id">
                        <a :href="getOccurrenceUrl.replace('occurrence_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                </ul>
            </div> -->
            <!-- <div v-else> -->
                <p>Are you sure you want to delete occurrence "{{ delOccurrence.name }}"?</p>
            <!-- </div> -->
            <div slot="header">
                <h4 class="modal-title">Delete occurrence "{{ delOccurrence.name }}"</h4>
            </div>
            <div slot="footer">
                <btn @click="delModal=false">
                    Cancel
                </btn>
                <btn
                    type="danger"
                    :disabled="delDependencies.length !== 0"
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
import fieldRadio from '../Components/FormFields/fieldRadio'

import Fields from '../Components/Fields'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('fieldRadio', fieldRadio)

var YEAR_MIN = 1
var YEAR_MAX = (new Date()).getFullYear()

export default {
    mixins: [ Fields ],
    props: {
        isEditor: {
            type: Boolean,
            default: false,
        },
        occurrencesSearchApiUrl: {
            type: String,
            default: '',
        },
        getOccurrenceDepsByOccurrenceUrl: {
            type: String,
            default: '',
        },
        getOccurrenceUrl: {
            type: String,
            default: '',
        },
        showOccurrenceUrl: {
            type: String,
            default: '',
        },
        editOccurrenceUrl: {
            type: String,
            default: '',
        },
        delOccurrenceUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            model: {
                text_type: 'any',
            },
            originalModel: {},
            schema: {
                fields: {
                    text: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Text',
                        model: 'text',
                    },
                    text_type: {
                        type: 'radio',
                        label: 'Text search options:',
                        model: 'text_type',
                        values: [
                            { value: 'any', name: 'Match any words' },
                            { value: 'all', name: 'Match all words' },
                            { value: 'phrase', name: 'Match all words in correct order' },
                        ],
                    },
                    meter: this.createMultiSelect('Meter'),
                    subject: this.createMultiSelect('Subject'),
                    manuscript_content: this.createMultiSelect('Manuscript Content', {model: 'manuscript_content'}),
                    patron: this.createMultiSelect('Patron'),
                    scribe: this.createMultiSelect('Scribe'),
                    year_from: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        model: 'year_from',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    genre: this.createMultiSelect('Genre'),
                }
            },
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            tableOptions: {
                headings: {
                    text: 'Text (matching verses only)',
                },
                filterable: false,
                // orderBy: {
                //     'column': 'name'
                // },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: [],
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
                },
                rowClassCallback: function(row) {
                    return row.public ? '' : 'warning'
                },
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
            delOccurrence: {
                id: 0,
                name: ''
            },
            delModal: false,
            delDependencies: [],
            alerts: [],
            textSearch: false,
        }
        if (this.isEditor) {
            data.schema.fields['public'] = this.createMultiSelect(
                'Public',
                null,
                {
                    customLabel: ({id, name}) => {
                        return name === 'true' ? 'Public only' : 'Internal only'
                    },
                }
            )
        }
        return data
    },
    computed: {
        tableColumns() {
            let columns = ['incipit', 'manuscript']
            if (this.textSearch) {
                columns.push('text')
            }
            if (this.isEditor) {
                columns.push('actions')
            }
            return columns
        },
    },
    mounted() {
        this.originalModel = JSON.parse(JSON.stringify(this.model))
    },
    methods: {
        constructFilterValues() {
            let result = {}
            if (this.model != null) {
                for (let fieldName of Object.keys(this.model)) {
                    if (this.schema.fields[fieldName].type === 'multiselectClear') {
                        result[fieldName] = this.model[fieldName]['id']
                    }
                    else if (fieldName === 'year_from') {
                        if (!('date' in result)) {
                            result['date'] = {}
                        }
                        result['date']['from'] = this.model[fieldName]
                    }
                    else if (fieldName === 'year_to') {
                        if (!('date' in result)) {
                            result['date'] = {}
                        }
                        result['date']['to'] = this.model[fieldName]
                    }
                    else {
                        result[fieldName] = this.model[fieldName]
                    }
                }
            }
            return result
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

            if (this.model != null) {
                for (let fieldName of Object.keys(this.model)) {
                    if (
                        this.model[fieldName] === null ||
                        this.model[fieldName] === '' ||
                        ((['year_from', 'year_to'].indexOf(fieldName) > -1) && isNaN(this.model[fieldName]))
                    ) {
                        delete this.model[fieldName]
                    }
                    let field = this.schema.fields[fieldName]
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        delete this.model[fieldName]
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
                // in the case a text checkbox was used, only send request if text is not null
                // filters are always in the same order, so we can compare serialization
                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues
                    if (!(this.lastChangedField == 'text_type' && (this.model.text == null || this.model.text == ''))) {
                        // TODO: prevent initial duplicate request
                        VueTables.Event.$emit('vue-tables.filter::filters', filterValues)
                    }
                }
            }, timeoutValue)
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
            this.model = JSON.parse(JSON.stringify(this.originalModel))
            this.onValidated(true)
        },
        del(row) {
            this.delOccurrence = row
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.get(this.getOccurrenceDepsByOccurrenceUrl.replace('occurrence_id', this.delOccurrence.id))
                .then((response) => {
                    this.delDependencies = response.data
                    this.delModal = true
                    this.openRequests--
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while checking for dependencies.'})
                    console.log(error)
                })
        },
        submitDelete() {
            this.openRequests++
            this.delModal = false
            axios.delete(this.delOccurrenceUrl.replace('occurrence_id', this.delOccurrence.id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Occurrence deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the occurrence.'})
                    console.log(error)
                })
        },
        onData(data) {
            // Check whether column 'text' should be displayed
            if (this.model.text != null && this.model.text != '') {
                this.textSearch = true
            }
            else {
                this.textSearch = false
            }
            // Update aggregation fields
            for (let fieldName of Object.keys(this.schema.fields)) {
                let field = this.schema.fields[fieldName]
                if (field.type == 'multiselectClear') {
                    let values = data.aggregation[fieldName] == null ? [] : data.aggregation[fieldName].sort(this.sortByName)
                    field.values = values
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        this.dependencyField(field)
                    }
                    else {
                        this.enableField(field)
                    }
                }
            }
            this.openRequests--
        }
    }
}
</script>
