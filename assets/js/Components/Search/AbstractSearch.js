window.axios = require('axios')

import qs from 'qs'

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import VueTables from 'vue-tables-2'
import * as uiv from 'uiv'

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Alerts from '../Alerts'
import Delete from '../Edit/Modals/Delete'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('deleteModal', Delete)

const YEAR_MIN = 1
const YEAR_MAX = (new Date()).getFullYear()

export default {
    props: {
        isEditor: {
            type: Boolean,
            default: false,
        },
        initUrls: {
            type: String,
            default: '',
        },
        initData: {
            type: String,
            default: '',
        },
    },
    data () {
        return {
            urls: JSON.parse(this.initUrls),
            model: {},
            originalModel: {},
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            openRequests: 0,
            tableCancel: null,
            actualRequest: false,
            initialized: false,
            historyRequest: false,
            // used to set timeout on free input fields
            lastChangedField: '',
            // used to only send requests after timeout when inputting free input fields
            inputCancel: null,
            // Remove requesting the same data that is already displayed
            oldFilterValues: {},
            deleteModal: false,
            delDependencies: {},
            alerts: [],
            textSearch: false,
            commentSearch: false,
            aggregation: {},
            lastOrder: null,
        }
    },
    mounted() {
        if (this.isEditor) {
            this.$refs.form.schema.fields['public'] = this.createMultiSelect(
                'Public',
                null,
                {
                    customLabel: ({id, name}) => {
                        return name === 'true' ? 'Public only' : 'Internal only'
                    },
                }
            )
        }
        this.originalModel = JSON.parse(JSON.stringify(this.model))
        window.onpopstate = ((event) => {this.popHistory(event)})
    },
    methods: {
        constructFilterValues() {
            let result = {}
            if (this.model != null) {
                for (let fieldName of Object.keys(this.model)) {
                    if (this.schema.fields[fieldName].type === 'multiselectClear') {
                        if (this.model[fieldName] != null) {
                            result[fieldName] = this.model[fieldName]['id']
                        }
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
                    else if (fieldName === 'text' || fieldName === 'comment') {
                        result[fieldName] = this.model[fieldName].trim()
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

            // Remove column ordering if text or comment is searched, reset when no value is provided
            // Do not refresh twice
            if (this.lastChangedField == 'text' || this.lastChangedField == 'comment') {
                this.actualRequest = false
                if (this.model[this.lastChangedField] == null || this.model[this.lastChangedField == '']) {
                    if (this.lastOrder == null) {
                        this.$refs.resultTable.setOrder(this.defaultOrdering, true)
                    }
                    else {
                        let asc = (this.lastOrder.hasOwnProperty('ascending') && this.lastOrder['ascending'])
                        this.$refs.resultTable.setOrder(this.lastOrder.column, asc)
                    }
                }
                else {
                    this.lastOrder = JSON.parse(JSON.stringify(this.$refs.resultTable.orderBy))
                    this.$refs.resultTable.setOrder(null)
                }
            }

            // Don't get new data if last changed field is text_type and text is null or empty
            // else: remove column ordering
            if (this.lastChangedField == 'text_type') {
                if (this.model.text == null || this.model.text == '') {
                    this.actualRequest = false
                }
                else {
                    this.actualRequest = false
                    this.$refs.resultTable.setOrder(null)
                    this.actualRequest = true
                }
            }
            else {
                this.actualRequest = true
            }

            // Don't get new data if history is being popped
            if (this.historyRequest) {
                this.actualRequest = false
            }


            this.inputCancel = window.setTimeout(() => {
                this.inputCancel = null
                let filterValues = this.constructFilterValues()
                // only send request if the filters have changed
                // filters are always in the same order, so we can compare serialization
                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues)
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
        onData(data) {
            this.aggregation = data.aggregation

            // Check whether column 'title/text' should be displayed
            if (
                data.data.length > 0
                && (data.data[0].hasOwnProperty('text') || data.data[0].hasOwnProperty('title'))
            ) {
                this.textSearch = true
            }
            else {
                this.textSearch = false
            }

            // Check whether comment column(s) should be displayed
            if (
                data.data.length > 0
                && (data.data[0].hasOwnProperty('public_comment') || data.data[0].hasOwnProperty('private_comment'))
            ) {
                this.commentSearch = true
            }
            else {
                this.commentSearch = false
            }
        },
        onLoaded(data) {
            // Update model and ordering if not initialized or history request
            if (!this.initialized) {
                this.init(true)
                this.initialized = true
            }
            if (this.historyRequest) {
                this.init(this.historyRequest === 'init')
                this.historyRequest = false
            }

            // Update aggregation fields
            for (let fieldName of Object.keys(this.schema.fields)) {
                let field = this.schema.fields[fieldName]
                if (field.type === 'multiselectClear') {
                    let values = this.aggregation[fieldName] == null ? [] : this.aggregation[fieldName].sort(this.sortByName)
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
        },
        pushHistory(data) {
            history.pushState(data, document.title, document.location.href.split('?')[0] + '?' + qs.stringify(data))
        },
        popHistory(event) {
            // set querystring
            if (window.location.href.split('?', 2).length > 1) {
                this.historyRequest = window.location.href.split('?', 2)[1]
            }
            else {
                this.historyRequest = 'init'
            }
            this.$refs.resultTable.refresh()
        },
        init(initial) {
            // set model
            let params = qs.parse(window.location.href.split('?', 2)[1])
            let model = JSON.parse(JSON.stringify(this.originalModel))
            if (params.hasOwnProperty('filters')) {
                Object.keys(params['filters']).forEach((key) => {
                    if (key === 'date') {
                        if (params['filters']['date'].hasOwnProperty('from')) {
                            model['year_from'] = Number(params['filters']['date']['from'])
                        }
                        if (params['filters']['date'].hasOwnProperty('to')) {
                            model['year_to'] = Number(params['filters']['date']['to'])
                        }
                    }
                    else if (this.schema.fields.hasOwnProperty(key)) {
                        if (this.schema.fields[key].type === 'multiselectClear') {
                            model[key] = this.aggregation[key].filter(v => String(v.id) === params['filters'][key])[0]
                        }
                        else {
                            model[key] = params['filters'][key]
                        }
                    }
                }, this)
            }
            this.model = model

            // set oldFilterValues
            this.oldFilterValues = this.constructFilterValues()

            // set table ordering
            this.actualRequest = false
            if (params.hasOwnProperty('orderBy')) {
                let asc = (params.hasOwnProperty('ascending') && params['ascending'])
                this.$refs.resultTable.setOrder(params['orderBy'], asc)
            }
            else if (
                params.hasOwnProperty('filters')
                && (
                (params['filters'].hasOwnProperty('text') && params['filters']['text'] != null && params['filters']['text'] != '')
                || (params['filters'].hasOwnProperty('comment') && params['filters']['comment'] != null && params['filters']['comment'] != '')
                )
            ) {
                this.$refs.resultTable.setOrder(null)
            }
            else {
                this.$refs.resultTable.setOrder(this.defaultOrdering, true)
            }
        },
    },
    requestFunction (data) {
        // Remove unused parameters
        delete data['query']
        delete data['byColumn']
        if (!data.hasOwnProperty('orderBy')) {
            delete data['ascending']
        }
        // Add filter values if necessary
        data['filters'] = this.$parent.constructFilterValues()
        if (data['filters'] == null || data['filters'] == '') {
            delete data['filters']
        }
        this.$parent.openRequests++
        if (!this.$parent.initialized) {
            return new Promise((resolve, reject) => {
                let parsedData = JSON.parse(this.$parent.initData)
                this.$emit('data', parsedData)
                resolve({
                    data : {
                        data: parsedData.data,
                        count: parsedData.count
                    }
                })
            })
        }
        if (!this.$parent.actualRequest) {
            return new Promise((resolve, reject) => {
                resolve({
                    data : {
                        data: this.data,
                        count: this.count
                    }
                })
            })
        }
        if (this.$parent.historyRequest) {
            if (this.$parent.openRequests > 1) {
                this.$parent.tableCancel('Operation canceled by newer request')
            }
            let url = this.url
            if (this.$parent.historyRequest !== 'init') {
                url += '?' + this.$parent.historyRequest
            }
            return axios.get(url, {
                cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
            })
                .then( (response) => {
                    this.$emit('data', response.data)
                    return response
                })
                .catch(function (error) {
                    this.$parent.historyRequest = false
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
        this.$parent.pushHistory(data)
        if (this.$parent.openRequests > 1) {
            this.$parent.tableCancel('Operation canceled by newer request')
        }
        return axios.get(this.url, {
            params: data,
            paramsSerializer: qs.stringify,
            cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
        })
            .then( (response) => {
                this.$emit('data', response.data)
                return response
            })
            .catch(function (error) {
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
    YEAR_MIN: YEAR_MIN,
    YEAR_MAX: YEAR_MAX,
}
