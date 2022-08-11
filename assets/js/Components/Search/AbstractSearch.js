import qs from 'qs';

import Vue from 'vue';
import VueFormGenerator from 'vue-form-generator';
import VueMultiselect from 'vue-multiselect';
import VueTables from 'vue-tables-2';
import * as uiv from 'uiv';

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear.vue';
import Delete from '../Edit/Modals/Delete.vue';
import CollectionManager from './CollectionManager.vue';

window.axios = require('axios');

Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(VueTables.ServerTable);

Vue.component('MultiSelect', VueMultiselect);
Vue.component('FieldMultiselectClear', fieldMultiselectClear);
Vue.component('DeleteModal', Delete);
Vue.component('CollectionManager', CollectionManager);

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
    data() {
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
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
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
        };
    },
    computed: {
        showReset() {
            for (const key of Object.keys(this.model)) {
                if (
                    (
                        this.model[key] != null
                        && (
                            !(key in this.originalModel)
                            || this.model[key] !== this.originalModel[key]
                        )
                    )
                    || (this.model[key] == null && (key in this.originalModel) && this.originalModel[key] != null)
                ) {
                    return true;
                }
            }
            return false;
        },
        notEmptyFields() {
            const show = [];
            if (this.schema.fields !== undefined) {
                Object.keys(this.schema.fields).forEach((key) => {
                    const load = this.schema.fields[key];
                    const currentModel = load.model;
                    const modelValue = this.model[currentModel];
                    const filterLabel = load.label;
                    if (modelValue !== undefined
                        && currentModel !== 'text_combination'
                        && currentModel !== 'text_fields'
                        && currentModel !== 'date_search_type'
                        && currentModel !== 'title_type'
                        && !currentModel.endsWith('_op')) {
                        if (Array.isArray(modelValue)) {
                            if (modelValue.length) {
                                show.push({
                                    key: currentModel,
                                    value: modelValue,
                                    label: filterLabel,
                                    type: 'array',
                                });
                            }
                        } else if (modelValue.name === undefined) {
                            show.push({
                                key: currentModel,
                                value: [{ name: modelValue }],
                                label: filterLabel,
                                type: typeof modelValue,
                            });
                        } else {
                            show.push({
                                key: currentModel,
                                value: [modelValue],
                                label: filterLabel,
                                type: typeof modelValue,
                            });
                        }
                    }
                });
            }
            return show;
        },
    },
    mounted() {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        window.onpopstate = ((event) => { this.popHistory(event); });
        this.updateCountRecords();
    },
    methods: {
        constructFilterValues() {
            const result = {};
            if (this.model != null) {
                for (const fieldName of Object.keys(this.model)) {
                    if (this.schema.fields[fieldName].type === 'multiselectClear') {
                        if (this.model[fieldName] != null) {
                            if (Array.isArray(this.model[fieldName])) {
                                const ids = [];
                                for (const value of this.model[fieldName]) {
                                    ids.push(value.id);
                                }
                                result[fieldName] = ids;
                            } else {
                                result[fieldName] = this.model[fieldName].id;
                            }
                        }
                    } else if (fieldName === 'year_from') {
                        if (!('date' in result)) {
                            result.date = {};
                        }
                        result.date.from = this.model[fieldName];
                    } else if (fieldName === 'year_to') {
                        if (!('date' in result)) {
                            result.date = {};
                        }
                        result.date.to = this.model[fieldName];
                    } else if (fieldName === 'text' || fieldName === 'comment') {
                        result[fieldName] = this.model[fieldName].trim();
                    } else {
                        result[fieldName] = this.model[fieldName];
                    }
                }
            }
            return result;
        },
        modelUpdated(value, fieldName) {
            this.lastChangedField = fieldName;
        },
        onValidated(isValid) {
            // do nothin but cancelling requests if invalid
            if (!isValid) {
                if (this.inputCancel !== null) {
                    window.clearTimeout(this.inputCancel);
                    this.inputCancel = null;
                }
                return;
            }

            if (this.model != null) {
                for (const fieldName of Object.keys(this.model)) {
                    if (
                        this.model[fieldName] == null
                        || this.model[fieldName] === ''
                        || ((['year_from', 'year_to'].indexOf(fieldName) > -1) && Number.isNaN(this.model[fieldName]))
                    ) {
                        delete this.model[fieldName];
                    }
                    const field = this.schema.fields[fieldName];
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        delete this.model[fieldName];
                    }
                }
            }

            if ('year_from' in this.schema.fields && 'year_to' in this.schema.fields) {
                // set year min and max values
                if (this.model.year_from != null) {
                    this.schema.fields.year_to.min = Math.max(YEAR_MIN, this.model.year_from);
                } else {
                    this.schema.fields.year_to.min = YEAR_MIN;
                }
                if (this.model.year_to != null) {
                    this.schema.fields.year_from.max = Math.min(YEAR_MAX, this.model.year_to);
                } else {
                    this.schema.fields.year_from.max = YEAR_MAX;
                }
            }

            // Cancel timeouts caused by input requests not long ago
            if (this.inputCancel != null) {
                window.clearTimeout(this.inputCancel);
                this.inputCancel = null;
            }

            // Send requests to update filters and result table
            // Add a delay to requests originated from input field changes to limit the number of requests
            let timeoutValue = 0;
            if (this.lastChangedField !== '' && this.schema.fields[this.lastChangedField].type === 'input') {
                timeoutValue = 1000;
            }

            // Remove column ordering if text or comment is searched, reset when no value is provided
            // Do not refresh twice
            if (this.lastChangedField === 'text' || this.lastChangedField === 'comment') {
                this.actualRequest = false;
                if (this.model[this.lastChangedField] == null || this.model[this.lastChangedField === '']) {
                    if (this.lastOrder == null) {
                        this.$refs.resultTable.setOrder(this.defaultOrdering, true);
                    } else {
                        const asc = ('ascending' in this.lastOrder && this.lastOrder.ascending);
                        this.$refs.resultTable.setOrder(this.lastOrder.column, asc);
                    }
                } else {
                    this.lastOrder = JSON.parse(JSON.stringify(this.$refs.resultTable.options.orderBy));
                    this.$refs.resultTable.setOrder(null);
                }
            }

            // Don't get new data if last changed field is text_type and text is null or empty
            // else: remove column ordering
            if (this.lastChangedField === 'text_type') {
                if (this.model.text == null || this.model.text === '') {
                    this.actualRequest = false;
                } else {
                    this.actualRequest = false;
                    this.$refs.resultTable.setOrder(null);
                    this.actualRequest = true;
                }
            } else {
                this.actualRequest = true;
            }

            // Don't get new data if history is being popped
            if (this.historyRequest) {
                this.actualRequest = false;
            }

            this.inputCancel = window.setTimeout(() => {
                this.inputCancel = null;
                const filterValues = this.constructFilterValues();
                // only send request if the filters have changed
                // filters are always in the same order, so we can compare serialization
                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues;
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues);
                }
            }, timeoutValue);
        },
        sortByName(a, b) {
            // Move special filter values to the top
            if (a.id === -1) {
                return -1;
            }
            if (b.id === -1) {
                return 1;
            }
            // Place true before false
            if (a.name === 'false' && b.name === 'true') {
                return 1;
            }
            if (a.name === 'true' && b.name === 'false') {
                return -1;
            }
            if (
                (typeof (a.name) === 'string' || a.name instanceof String)
                && (typeof (b.name) === 'string' || b.name instanceof String)
            ) {
                // Numeric (a.o. shelf number) (e.g., 571A)
                let first = a.name.match(this.numRegex);
                let second = b.name.match(this.numRegex);
                if (first && second) {
                    if (parseInt(first[1], 10) < parseInt(second[1], 10)) {
                        return -1;
                    }
                    if (parseInt(first[1], 10) > parseInt(second[1], 10)) {
                        return 1;
                    }
                    // let the string compare below handle cases where the numeric part is equal, but the rest not
                }
                // RGK (e.g., II.513)
                first = a.name.match(this.rgkRegex);
                second = b.name.match(this.rgkRegex);
                if (first && second) {
                    if (first[1] < second[1]) {
                        return -1;
                    }
                    if (first[1] > second[1]) {
                        return 1;
                    }
                    return first[2] - second[2];
                }
                // VGH (e.g., 513.B)
                first = a.name.match(this.vghRegex);
                second = b.name.match(this.vghRegex);
                if (first) {
                    if (second) {
                        if (first[1] !== second[1]) {
                            return first[1] - second[1];
                        }
                        if (first[2] < second[2]) {
                            return -1;
                        }
                        if (first[2] > second[2]) {
                            return 1;
                        }
                        return 0;
                    }
                    // place irregular vghs at the end
                    return -1;
                }
                if (second) {
                    // place irregular vghs at the end
                    return 1;
                }
                // Role with count (e.g., Patron (7))
                first = a.name.match(this.roleCountRegex);
                second = b.name.match(this.roleCountRegex);
                if (first && second) {
                    return second[1] - first[1];
                }
                // Greek
                first = a.name.match(this.greekRegex);
                second = b.name.match(this.greekRegex);
                if (first && second) {
                    if (this.removeGreekAccents(a.name) < this.removeGreekAccents(b.name)) {
                        return -1;
                    }
                    if (this.removeGreekAccents(a.name) > this.removeGreekAccents(b.name)) {
                        return 1;
                    }
                    return 0;
                }
                // AlphaNumRest (a.o. shelf number) (e.g., Γ 5 (Eustratiades 245))
                first = a.name.match(this.alphaNumRestRegex);
                second = b.name.match(this.alphaNumRestRegex);
                if (first && second) {
                    if (first[1] < second[1]) {
                        return -1;
                    }
                    if (first[1] > second[1]) {
                        return 1;
                    }
                    if (first[2] !== second[2]) {
                        return first[2] - second[2];
                    }
                    if (first[3] < second[3]) {
                        return -1;
                    }
                    if (first[3] > second[3]) {
                        return 1;
                    }
                    return 0;
                    // let the string compare below handle cases where the numeric part is equal, but the rest not
                }
            }
            // Default
            if (a.name < b.name) {
                return -1;
            }
            if (a.name > b.name) {
                return 1;
            }
            return 0;
        },
        resetAllFilters() {
            this.model = JSON.parse(JSON.stringify(this.originalModel));
            this.onValidated(true);
        },
        onData(data) {
            this.data = data;

            // Check whether column 'title/text' should be displayed
            this.textSearch = false;
            for (const item of data.data) {
                if (
                    'text' in item
                    || 'title' in item
                    || 'title_GR' in item
                    || 'title_LA' in item
                ) {
                    this.textSearch = true;
                    break;
                }
            }

            // Check whether comment column(s) should be displayed
            this.commentSearch = false;
            for (const item of data.data) {
                if (
                    'public_comment' in item
                    || 'private_comment' in item
                    || 'palaeographical_info' in item
                    || 'contextual_info' in item
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
                this.initialized = true;
            }
            if (this.historyRequest) {
                this.init(this.historyRequest === 'init');
                this.historyRequest = false;
            }

            // Update aggregation fields
            for (const fieldName of Object.keys(this.schema.fields)) {
                const field = this.schema.fields[fieldName];
                if (field.type === 'multiselectClear') {
                    field.values = this.data.aggregation[fieldName] == null
                        ? []
                        : this.data.aggregation[fieldName].sort(this.sortByName);
                    field.originalValues = JSON.parse(JSON.stringify(field.values));
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        this.dependencyField(field);
                    } else {
                        this.enableField(field, null, true);
                    }
                }
                if (
                    field.multiDependency != null
                ) {
                    if (this.model[field.multiDependency] == null || this.model[field.multiDependency].length < 2) {
                        field.disabled = true;
                    } else {
                        field.disabled = false;
                    }
                }
            }

            // Update number of records text
            this.updateCountRecords();

            this.openRequests -= 1;
        },
        pushHistory(data) {
            const filteredData = JSON.parse(JSON.stringify(data));
            // Remove default values
            if ('limit' in filteredData && filteredData.limit === 25) {
                delete filteredData.limit;
            }
            if ('page' in filteredData && filteredData.page === 1) {
                delete filteredData.page;
            }
            if (
                'orderBy' in filteredData
                && filteredData.orderBy === this.tableOptions.orderBy.column
                && 'ascending' in filteredData
                && filteredData.ascending === 1
            ) {
                delete filteredData.orderBy;
                delete filteredData.ascending;
            }
            if ('filters' in filteredData) {
                for (const fieldName of Object.keys(this.schema.fields)) {
                    if (fieldName in filteredData.filters) {
                        const field = this.schema.fields[fieldName];
                        if (fieldName in this.originalModel) {
                            if (this.model[fieldName] === this.originalModel[fieldName]) {
                                delete filteredData.filters[fieldName];
                            }
                        }
                        if (field.multiDependency != null) {
                            if (
                                this.model[field.multiDependency] == null
                                || this.model[field.multiDependency].length < 2
                            ) {
                                delete filteredData.filters[fieldName];
                            }
                        }
                    }
                }
            }
            window.history.pushState(
                filteredData,
                document.title,
                `${document.location.href.split('?')[0]}?${qs.stringify(filteredData)}`,
            );
        },
        popHistory() {
            // set querystring
            if (window.location.href.split('?', 2).length > 1) {
                [, this.historyRequest] = window.location.href.split('?', 2);
            } else {
                this.historyRequest = 'init';
            }
            this.$refs.resultTable.refresh();
        },
        init() {
            // set model
            const params = qs.parse(window.location.href.split('?', 2)[1]);
            const model = JSON.parse(JSON.stringify(this.originalModel));
            if ('filters' in params) {
                for (const key of Object.keys(params.filters)) {
                    if (key === 'date') {
                        if ('from' in params.filters.date) {
                            model.year_from = Number(params.filters.date.from);
                        }
                        if ('to' in params.filters.date) {
                            model.year_to = Number(params.filters.date.to);
                        }
                    } else if (key in this.schema.fields) {
                        if (
                            this.schema.fields[key].type === 'multiselectClear'
                            && this.data.aggregation[key] != null
                        ) {
                            if (Array.isArray(params.filters[key])) {
                                model[key] = this.data.aggregation[key].filter(
                                    (v) => params.filters[key].includes(String(v.id)),
                                );
                            } else {
                                [model[key]] = this.data.aggregation[key].filter(
                                    (v) => String(v.id) === params.filters[key],
                                );
                            }
                        } else {
                            model[key] = params.filters[key];
                        }
                    }
                }
            }
            this.model = model;

            // set oldFilterValues
            this.oldFilterValues = this.constructFilterValues();

            // set table page
            if ('page' in params) {
                this.actualRequest = false;
                this.$refs.resultTable.setPage(params.page);
            }
            // set table ordering
            this.actualRequest = false;
            if ('orderBy' in params) {
                const asc = ('ascending' in params && params.ascending);
                this.$refs.resultTable.setOrder(params.orderBy, asc);
            } else if (
                'filters' in params
                && (
                    ('text' in params && params.filters.text != null && params.filters.text !== '')
                    || ('comment' in params && params.filters.comment != null && params.filters.comment !== '')
                )
            ) {
                this.$refs.resultTable.setOrder(null);
            } else {
                this.$refs.resultTable.setOrder(this.defaultOrdering, true);
            }
        },
        updateCountRecords() {
            const { table } = this.$refs.resultTable.$refs;
            if (!table.count) {
                this.countRecords = '';
                return;
            }
            const perPage = parseInt(table.limit, 10);

            const from = ((table.Page - 1) * perPage) + 1;
            const to = table.Page === table.totalPages ? table.count : from + perPage - 1;

            const parts = table.opts.texts.count.split('|');
            let i;
            if (table.count === 1) {
                i = Math.min(2, parts.length - 1);
            } else if (table.totalPages === 1) {
                i = Math.min(1, parts.length - 1);
            } else {
                i = 0;
            }

            this.countRecords = parts[i].replace('{count}', table.count)
                .replace('{from}', from)
                .replace('{to}', to);
        },
        isLoginError(error) {
            return error.message === 'Network Error';
        },
        collectionToggleAll() {
            let allChecked = true;
            for (const row of this.data.data) {
                if (!this.collectionArray.includes(row.id)) {
                    allChecked = false;
                    break;
                }
            }
            if (allChecked) {
                this.clearCollection();
            } else {
                for (const row of this.data.data) {
                    if (!this.collectionArray.includes(row.id)) {
                        this.collectionArray.push(row.id);
                    }
                }
            }
        },
        clearCollection() {
            this.collectionArray = [];
        },
        addManagementsToSelection(managementCollections) {
            this.openRequests += 1;
            window.axios.put(
                this.urls.managements_add,
                {
                    ids: this.collectionArray,
                    managements: managementCollections,
                },
            )
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'success',
                            message: 'Management collections added successfully.',
                        },
                    );
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'error',
                            message: 'Something went wrong while adding the management collections.',
                        },
                    );
                    console.error(error);
                });
        },
        removeManagementsFromSelection(managementCollections) {
            this.openRequests += 1;
            window.axios.put(
                this.urls.managements_remove,
                {
                    ids: this.collectionArray,
                    managements: managementCollections,
                },
            )
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'success',
                            message: 'Management collections removed successfully.',
                        },
                    );
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'error',
                            message: 'Something went wrong while removing the management collections.',
                        },
                    );
                    console.error(error);
                });
        },
        addManagementsToResults(managementCollections) {
            this.openRequests += 1;
            window.axios.put(
                this.urls.managements_add,
                {
                    filter: this.constructFilterValues(),
                    managements: managementCollections,
                },
            )
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'success',
                            message: 'Management collections added successfully.',
                        },
                    );
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'error',
                            message: 'Something went wrong while adding the management collections.',
                        },
                    );
                    console.error(error);
                });
        },
        removeManagementsFromResults(managementCollections) {
            this.openRequests += 1;
            window.axios.put(
                this.urls.managements_remove,
                {
                    filter: this.constructFilterValues(),
                    managements: managementCollections,
                },
            )
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'success',
                            message: 'Management collections removed successfully.',
                        },
                    );
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push(
                        {
                            type: 'error',
                            message: 'Something went wrong removing adding the management collections.',
                        },
                    );
                    console.error(error);
                });
        },
        greekFont(input) {
            // eslint-disable-next-line max-len
            return input.replace(/((?:[[.,(|+][[\].,():|+\- ]*)?[\u0370-\u03ff\u1f00-\u1fff]+(?:[[\].,():|+\- ]*[\u0370-\u03ff\u1f00-\u1fff]+)*(?:[[\].,():|+\- ]*[\].,):|])?)/g, '<span class="greek">$1</span>');
        },
        formatDate(input) {
            const date = new Date(input);
            return [
                `00${date.getDate()}`.slice(-2),
                `00${date.getMonth() + 1}`.slice(-2),
                date.getFullYear(),
            ].join('/');
        },
        removeGreekAccents(input) {
            const encoded = encodeURIComponent(input.normalize('NFD'));
            const stripped = encoded.replace(/%C[^EF]%[0-9A-F]{2}/gi, '');
            return decodeURIComponent(stripped).toLocaleLowerCase();
        },
        deleteActiveFilter({ key, valueIndex }) {
            if (key === 'year_from' || key === 'year_to') {
                this.model[key] = undefined;
            } else if (valueIndex === -1) {
                this.model[key] = '';
            } else {
                this.model[key].splice(valueIndex, 1);
            }
            this.lastChangedField = '';
            this.onValidated(true);
        },
    },
    requestFunction(data) {
        const params = data;
        const self = this;
        // Remove unused parameters
        delete params.query;
        delete params.byColumn;
        if (!('orderBy' in params)) {
            delete params.ascending;
        }
        const searchApp = this.$parent.$parent;
        // Add filter values if necessary
        params.filters = searchApp.constructFilterValues();
        if (params.filters == null || params.filters === '') {
            delete params.filters;
        }
        searchApp.openRequests += 1;
        if (!searchApp.initialized) {
            return new Promise((resolve) => {
                searchApp.onData(searchApp.data);
                resolve({
                    data: {
                        data: searchApp.data.data,
                        count: searchApp.data.count,
                    },
                });
            });
        }
        if (!searchApp.actualRequest) {
            return new Promise((resolve) => {
                resolve({
                    data: {
                        data: this.data,
                        count: this.count,
                    },
                });
            });
        }
        if (searchApp.historyRequest) {
            if (searchApp.openRequests > 1 && searchApp.tableCancel != null) {
                searchApp.tableCancel('Operation canceled by newer request');
            }
            let { url } = this;
            if (searchApp.historyRequest !== 'init') {
                url = `${url}?${searchApp.historyRequest}`;
            }
            return window.axios.get(url, {
                cancelToken: new window.axios.CancelToken((c) => { searchApp.tableCancel = c; }),
            })
                .then((response) => {
                    searchApp.onData(response.data);
                    return response;
                })
                .catch((error) => {
                    searchApp.historyRequest = false;
                    searchApp.openRequests -= 1;
                    if (window.axios.isCancel(error)) {
                        // Return the current data if the request is cancelled
                        return {
                            data: {
                                data: self.data,
                                count: self.count,
                            },
                        };
                    }
                    searchApp.alerts.push({
                        type: 'error',
                        // eslint-disable-next-line max-len
                        message: 'Something went wrong while processing your request. Please verify your input is valid.',
                    });
                    console.error(error);
                    // Return the current data
                    return {
                        data: {
                            data: self.data,
                            count: self.count,
                        },
                    };
                });
        }
        if (!searchApp.noHistory) {
            searchApp.pushHistory(params);
        } else {
            searchApp.noHistory = false;
        }

        if (searchApp.openRequests > 1 && searchApp.tableCancel != null) {
            searchApp.tableCancel('Operation canceled by newer request');
        }
        return window.axios.get(this.url, {
            params,
            paramsSerializer: qs.stringify,
            cancelToken: new window.axios.CancelToken((c) => { searchApp.tableCancel = c; }),
        })
            .then((response) => {
                searchApp.alerts = [];
                searchApp.onData(response.data);
                return response;
            })
            .catch((error) => {
                if (window.axios.isCancel(error)) {
                    // Return the current data if the request is cancelled
                    return {
                        data: {
                            data: this.data,
                            count: this.count,
                        },
                    };
                }
                searchApp.alerts.push({
                    type: 'error',
                    message: 'Something went wrong while processing your request. Please verify your input is valid.',
                });
                console.error(error);
                // Return the current data
                return {
                    data: {
                        data: self.data,
                        count: self.count,
                    },
                };
            });
    },
    YEAR_MIN,
    YEAR_MAX,
};
