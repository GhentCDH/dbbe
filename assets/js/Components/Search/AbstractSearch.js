window.axios = require('axios');

import qs from 'qs'

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import VueTables from 'vue-tables-2'
import * as uiv from 'uiv'

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Delete from '../Edit/Modals/Delete'
import CollectionManager from './CollectionManager'

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(VueTables.ServerTable);

Vue.component('multiselect', VueMultiselect);
Vue.component('fieldMultiselectClear', fieldMultiselectClear);
Vue.component('deleteModal', Delete);
Vue.component('collectionManager', CollectionManager);

const YEAR_MIN = 1;
const YEAR_MAX = (new Date()).getFullYear();

export default {
    props: {
        isEditor: {
            type: Boolean,
            default: false,
        },
        isViewInternal: {
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
        initIdentifiers: {
            type: String,
            default: '',
        },
        initManagements: {
            type: String,
            default: '',
        },
    },
    data () {
        return {
            urls: JSON.parse(this.initUrls),
            data: JSON.parse(this.initData),
            identifiers: JSON.parse(this.initIdentifiers),
            managements: JSON.parse(this.initManagements),
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
            // prevent the creation of a browser history item
            noHistory: false,
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
            countRecords: '',
            numRegex: /^(\d+)/,
            rgkRegex: /^(I{1,3})[.]([\d]+)(?:, I{1,3}[.][\d]+)*$/,
            vghRegex: /^([\d]+)[.]([A-Z])(?:, [\d]+[.][A-Z])*$/,
            roleCountRegex: /^(?:Patron|Related|Scribe)[ ][(](\d+)[)]$/,
            greekRegex: /^([\u0370-\u03ff\u1f00-\u1fff ]*)$/,
            alphaNumRestRegex: /^([^\d]*)(\d+)(.*)$/,
            collectionArray: [],
        }
    },
    computed: {
        showReset() {
            for (let key in this.model) {
                if (this.model.hasOwnProperty(key)) {
                    if (
                        (this.model[key] != null && (!(key in this.originalModel) || this.model[key] !== this.originalModel[key]))
                        || (this.model[key] == null && (key in this.originalModel) && this.originalModel[key] != null)
                    ) {
                        return true
                    }
                }
            }
            return false
        },
    },
    mounted () {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        window.onpopstate = ((event) => {this.popHistory(event)});
        this.updateCountRecords()
    },
    methods: {
        constructFilterValues() {
            let result = {};
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
        onValidated(isValid) {
            // do nothin but cancelling requests if invalid
            if (!isValid) {
                if (this.inputCancel !== null) {
                    window.clearTimeout(this.inputCancel);
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
                    let field = this.schema.fields[fieldName];
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        delete this.model[fieldName]
                    }
                }
            }

            if ('year_from' in this.schema.fields && 'year_to' in this.schema.fields) {
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
            }

            // Cancel timeouts caused by input requests not long ago
            if (this.inputCancel != null) {
                window.clearTimeout(this.inputCancel);
                this.inputCancel = null
            }

            // Send requests to update filters and result table
            // Add a delay to requests originated from input field changes to limit the number of requests
            let timeoutValue = 0;
            if (this.lastChangedField !== '' && this.schema.fields[this.lastChangedField].type === 'input') {
                timeoutValue = 1000
            }

            // Remove column ordering if text or comment is searched, reset when no value is provided
            // Do not refresh twice
            if (this.lastChangedField === 'text' || this.lastChangedField === 'comment') {
                this.actualRequest = false;
                if (this.model[this.lastChangedField] == null || this.model[this.lastChangedField === '']) {
                    if (this.lastOrder == null) {
                        this.$refs.resultTable.setOrder(this.defaultOrdering, true)
                    }
                    else {
                        let asc = (this.lastOrder.hasOwnProperty('ascending') && this.lastOrder['ascending']);
                        this.$refs.resultTable.setOrder(this.lastOrder.column, asc)
                    }
                }
                else {
                    this.lastOrder = JSON.parse(JSON.stringify(this.$refs.resultTable.orderBy));
                    this.$refs.resultTable.setOrder(null)
                }
            }

            // Don't get new data if last changed field is text_type and text is null or empty
            // else: remove column ordering
            if (this.lastChangedField === 'text_type') {
                if (this.model.text == null || this.model.text === '') {
                    this.actualRequest = false
                }
                else {
                    this.actualRequest = false;
                    this.$refs.resultTable.setOrder(null);
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
                this.inputCancel = null;
                let filterValues = this.constructFilterValues();
                // only send request if the filters have changed
                // filters are always in the same order, so we can compare serialization
                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues;
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
            // Place true before false
            if (a.name === 'false' && b.name === 'true') {
                return 1
            }
            if (a.name === 'true' && b.name === 'false') {
                return -1
            }
            if ((typeof(a.name) === 'string' || a.name instanceof String) && (typeof(b.name) === 'string' || b.name instanceof String)) {
                // Numeric (a.o. shelf number) (e.g., 571A)
                let first = a.name.match(this.numRegex);
                let second = b.name.match(this.numRegex);
                if (first && second) {
                    if (parseInt(first[1]) < parseInt(second[1])) {
                        return -1
                    }
                    if (parseInt(first[1]) > parseInt(second[1])) {
                        return 1
                    }
                    // let the string compare below handle cases where the numeric part is equal, but the rest not
                }
                // RGK (e.g., II.513)
                first = a.name.match(this.rgkRegex);
                second = b.name.match(this.rgkRegex);
                if (first && second) {
                    if (first[1] < second[1]) {
                        return -1
                    }
                    if (first[1] > second[1]) {
                        return 1
                    }
                    return first[2] - second[2]
                }
                // VGH (e.g., 513.B)
                first = a.name.match(this.vghRegex);
                second = b.name.match(this.vghRegex);
                if (first) {
                    if (second) {
                        if (first[1] !== second[1]) {
                            return first[1] - second[1]
                        }
                        if (first[2] < second[2]) {
                            return -1
                        }
                        if (first[2] > second[2]) {
                            return 1
                        }
                        return 0
                    }
                    // place irregular vghs at the end
                    return -1
                }
                if (second) {
                    // place irregular vghs at the end
                    return 1
                }
                // Role with count (e.g., Patron (7))
                first = a.name.match(this.roleCountRegex);
                second = b.name.match(this.roleCountRegex);
                if (first && second) {
                    return second[1] - first[1]
                }
                // Greek
                first = a.name.match(this.greekRegex);
                second = b.name.match(this.greekRegex);
                if (first && second) {
                    if (this.removeGreekAccents(a.name) < this.removeGreekAccents(b.name)) {
                        return -1
                    }
                    if (this.removeGreekAccents(a.name) > this.removeGreekAccents(b.name)) {
                        return 1
                    }
                    return 0
                }
                // AlphaNumRest (a.o. shelf number) (e.g., Γ 5 (Eustratiades 245))
                first = a.name.match(this.alphaNumRestRegex);
                second = b.name.match(this.alphaNumRestRegex);
                if (first && second) {
                    if (first[1] < second[1]) {
                        return -1
                    }
                    if (first[1] > second[1]) {
                        return 1
                    }
                    if (first[2] !== second[2]) {
                        return first[2] - second[2]
                    }
                    if (first[3] < second[3]) {
                        return -1
                    }
                    if (first[3] > second[3]) {
                        return 1
                    }
                    return 0
                    // let the string compare below handle cases where the numeric part is equal, but the rest not
                }
            }
            // Default
            if (a.name < b.name) {
                return -1
            }
            if (a.name > b.name) {
                return 1
            }
            return 0
        },
        resetAllFilters() {
            this.model = JSON.parse(JSON.stringify(this.originalModel));
            this.onValidated(true)
        },
        onData(data) {
            this.data = data;

            // Check whether column 'title/text' should be displayed
            this.textSearch = false;
            for (let item of data.data) {
                if (
                    item.hasOwnProperty('text')
                    || item.hasOwnProperty('title')
                    || item.hasOwnProperty('title_GR')
                    || item.hasOwnProperty('title_LA')
                ) {
                    this.textSearch = true;
                    break;
                }
            }

            // Check whether comment column(s) should be displayed
            this.commentSearch = false;
            for (let item of data.data) {
                if (
                    item.hasOwnProperty('public_comment')
                    || item.hasOwnProperty('private_comment')
                    || item.hasOwnProperty('palaeographical_info')
                    || item.hasOwnProperty('contextual_info')
                ) {
                    this.commentSearch = true;
                    break;
                }
            }
        },
        onLoaded() {
            // Update model and ordering if not initialized or history request
            if (!this.initialized) {
                this.init(true);
                this.initialized = true
            }
            if (this.historyRequest) {
                this.init(this.historyRequest === 'init');
                this.historyRequest = false
            }

            // Update aggregation fields
            for (let fieldName of Object.keys(this.schema.fields)) {
                let field = this.schema.fields[fieldName];
                if (field.type === 'multiselectClear') {
                    field.values = this.data.aggregation[fieldName] == null ? [] : this.data.aggregation[fieldName].sort(this.sortByName);
                    field.originalValues = JSON.parse(JSON.stringify(field.values));
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        this.dependencyField(field)
                    }
                    else {
                        this.enableField(field, null, true)
                    }
                }
            }

            // Update number of records text
            this.updateCountRecords();

            this.openRequests--
        },
        pushHistory(data) {
            history.pushState(data, document.title, document.location.href.split('?')[0] + '?' + qs.stringify(data))
        },
        popHistory() {
            // set querystring
            if (window.location.href.split('?', 2).length > 1) {
                this.historyRequest = window.location.href.split('?', 2)[1]
            }
            else {
                this.historyRequest = 'init'
            }
            this.$refs.resultTable.refresh()
        },
        init() {
            // set model
            let params = qs.parse(window.location.href.split('?', 2)[1]);
            let model = JSON.parse(JSON.stringify(this.originalModel));
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
                        if (this.schema.fields[key].type === 'multiselectClear' && this.data.aggregation[key] != null) {
                            model[key] = this.data.aggregation[key].filter(v => String(v.id) === params['filters'][key])[0]
                        }
                        else {
                            model[key] = params['filters'][key]
                        }
                    }
                }, this)
            }
            this.model = model;

            // set oldFilterValues
            this.oldFilterValues = this.constructFilterValues();

            // set table page
            if (params.hasOwnProperty('page')) {
                this.actualRequest = false;
                this.$refs.resultTable.setPage(params['page'])
            }
            // set table ordering
            this.actualRequest = false;
            if (params.hasOwnProperty('orderBy')) {
                let asc = (params.hasOwnProperty('ascending') && params['ascending']);
                this.$refs.resultTable.setOrder(params['orderBy'], asc)
            }
            else if (
                params.hasOwnProperty('filters')
                && (
                (params['filters'].hasOwnProperty('text') && params['filters']['text'] != null && params['filters']['text'] !== '')
                || (params['filters'].hasOwnProperty('comment') && params['filters']['comment'] != null && params['filters']['comment'] !== '')
                )
            ) {
                this.$refs.resultTable.setOrder(null)
            }
            else {
                this.$refs.resultTable.setOrder(this.defaultOrdering, true)
            }
        },
        updateCountRecords() {
            let table = this.$refs.resultTable;
            if (!table.count) {
                this.countRecords = '';
                return
            }
            let perPage = parseInt(table.limit);

            let from = ((table.Page-1) * perPage) + 1;
            let to = table.Page === table.totalPages ? table.count:from + perPage - 1;

            let parts = table.opts.texts.count.split('|');
            let i = Math.min(table.count === 1 ? 2 : table.totalPages === 1 ? 1 : 0, parts.length-1);

            this.countRecords = parts[i].replace('{count}', table.count)
                .replace('{from}', from)
                .replace('{to}', to)
        },
        isLoginError(error) {
            return error.message === 'Network Error'
        },
        collectionToggleAll() {
            let allChecked = true;
            for (let row of this.data.data) {
                if (!this.collectionArray.includes(row.id)) {
                    allChecked = false;
                    break
                }
            }
            if (allChecked) {
                this.clearCollection()
            }
            else {
                for (let row of this.data.data) {
                    if (!this.collectionArray.includes(row.id)) {
                        this.collectionArray.push(row.id)
                    }
                }
            }
        },
        clearCollection() {
            this.collectionArray = []
        },
        addManagementsToSelection(managementCollections) {
            this.openRequests++;
            axios.put(this.urls['managements_add'], {ids: this.collectionArray, 'managements': managementCollections})
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests--;
                    this.alerts.push({type: 'success', message: 'Management collections added successfully.'})
                })
                .catch((error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while adding the management collections.'});
                    console.log(error)
                })
        },
        removeManagementsFromSelection(managementCollections) {
            this.openRequests++;
            axios.put(this.urls['managements_remove'], {ids: this.collectionArray, 'managements': managementCollections})
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests--;
                    this.alerts.push({type: 'success', message: 'Management collections removed successfully.'})
                })
                .catch((error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while removing the management collections.'});
                    console.log(error)
                })
        },
        addManagementsToResults(managementCollections) {
            this.openRequests++;
            axios.put(this.urls['managements_add'], {filter: this.constructFilterValues(), 'managements': managementCollections})
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests--;
                    this.alerts.push({type: 'success', message: 'Management collections added successfully.'})
                })
                .catch((error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while adding the management collections.'});
                    console.log(error)
                })
        },
        removeManagementsFromResults(managementCollections) {
            this.openRequests++;
            axios.put(this.urls['managements_remove'], {filter: this.constructFilterValues(), 'managements': managementCollections})
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests--;
                    this.alerts.push({type: 'success', message: 'Management collections removed successfully.'})
                })
                .catch((error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong removing adding the management collections.'});
                    console.log(error)
                })
        },
        greekFont(input) {
            return input.replace(/((?:[[.,(|+][[\].,():|+\- ]*)?[\u0370-\u03ff\u1f00-\u1fff]+(?:[[\].,():|+\- ]*[\u0370-\u03ff\u1f00-\u1fff]+)*(?:[[\].,():|+\- ]*[\].,):|])?)/g, '<span class="greek">$1</span>');
        },
        formatDate(input) {
            const date = new Date(input);
            return ('00' + date.getDate()).slice(-2) + '/' + ('00' + (date.getMonth() + 1)).slice(-2) + '/' + date.getFullYear();
        },
        removeGreekAccents(input) {
            let encoded = encodeURIComponent(input.normalize('NFD'));
            let stripped = encoded.replace(/%C[^EF]%[0-9A-F]{2}/gi, '');
            return decodeURIComponent(stripped).toLocaleLowerCase();
        },
    },
    requestFunction (data) {
        // Remove unused parameters
        delete data['query'];
        delete data['byColumn'];
        if (!data.hasOwnProperty('orderBy')) {
            delete data['ascending']
        }
        // Add filter values if necessary
        data['filters'] = this.$parent.constructFilterValues();
        if (data['filters'] == null || data['filters'] === '') {
            delete data['filters']
        }
        this.$parent.openRequests++;
        if (!this.$parent.initialized) {
            return new Promise((resolve) => {
                this.$emit('data', this.$parent.data);
                resolve({
                    data : {
                        data: this.$parent.data.data,
                        count: this.$parent.data.count
                    }
                })
            })
        }
        if (!this.$parent.actualRequest) {
            return new Promise((resolve) => {
                resolve({
                    data : {
                        data: this.data,
                        count: this.count
                    }
                })
            })
        }
        if (this.$parent.historyRequest) {
            if (this.$parent.openRequests > 1 && this.$parent.tableCancel != null) {
                this.$parent.tableCancel('Operation canceled by newer request')
            }
            let url = this.url;
            if (this.$parent.historyRequest !== 'init') {
                url += '?' + this.$parent.historyRequest
            }
            return axios.get(url, {
                cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
            })
                .then( (response) => {
                    this.$emit('data', response.data);
                    return response
                })
                .catch(function (error) {
                    this.$parent.historyRequest = false;
                    this.$parent.openRequests--;
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
        if (!this.$parent.noHistory) {
            this.$parent.pushHistory(data)
        } else {
            this.$parent.noHistory = false;
        }

        if (this.$parent.openRequests > 1 && this.$parent.tableCancel != null) {
            this.$parent.tableCancel('Operation canceled by newer request')
        }
        return axios.get(this.url, {
            params: data,
            paramsSerializer: qs.stringify,
            cancelToken: new axios.CancelToken((c) => {this.$parent.tableCancel = c})
        })
            .then( (response) => {
                this.$parent.alerts = [];
                this.$emit('data', response.data);
                return response
            })
            .catch(function (error) {
                if (axios.isCancel(error)) {
                    // Return the current data if the request is cancelled
                    return {
                        data: {
                            data: this.data,
                            count: this.count
                        }
                    }
                }
                this.$parent.alerts.push({
                    type: 'error',
                    message: 'Something went wrong while processing your request. Please verify your input is valid.'
                });
                console.log(error);
                // Return the current data
                return {
                    data: {
                        data: this.data,
                        count: this.count
                    }
                };
            }.bind(this))
    },
    YEAR_MIN: YEAR_MIN,
    YEAR_MAX: YEAR_MAX,
}
