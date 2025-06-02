import qs from 'qs';

import Vue from 'vue/dist/vue.js';
import {dependencyField, enableField,removeGreekAccents} from "../FormFields/formFieldUtils";
import VueMultiselect from 'vue-multiselect';
import VueTables from 'vue-tables-2';
import * as uiv from 'uiv';

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear.vue';
import Delete from '../Edit/Modals/Delete.vue';
import CollectionManager from './CollectionManager.vue';
import fieldCheckboxes from '../FormFields/fieldCheckboxes.vue';

import { YEAR_MIN, YEAR_MAX, changeMode } from './utils';
import axios from 'axios';
window.axios = axios;
Vue.use(uiv);

Vue.component('MultiSelect', VueMultiselect);
Vue.component('FieldMultiselectClear', fieldMultiselectClear);
Vue.component('DeleteModal', Delete);
Vue.component('CollectionManager', CollectionManager);
Vue.component('FieldCheckboxes', fieldCheckboxes);

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
            lemmaSearch: false,
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
        fields() {
            const res = {};
            if (this.schema && this.schema.fields) {
                Object.keys(this.schema.fields).forEach((key, index) => {
                    const field = this.schema.fields[key];
                    if (!field.multiple || field.multi === true) {
                        res[field.model] = field;
                    }
                });
            }
            if (this.schema && this.schema.groups) {
                this.schema.groups.forEach((group) => {
                    if (group.fields !== undefined) {
                        group.fields.forEach((field) => {
                            if (!this.multiple || field.multi === true) {
                                res[field.model] = field;
                            }
                        });
                    }
                });
            }
            return res;
        },
        notEmptyFields() {
            const show = [];
            if (this.schema.fields !== undefined) {
                Object.keys(this.schema.fields).forEach((key) => {
                    show.push(...this.addActiveFilter(key));
                });
            }
            if (this.schema.groups !== undefined) {
                this.schema.groups.forEach((group) => {
                    if (group.fields !== undefined) {
                        group.fields.forEach((key) => {
                            show.push(...this.addActiveFilter(key.model));
                        });
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
    watch: {
        'model.text_mode': function (value, oldValue) {
            this.changeTextMode(value, oldValue, 'text');
        },
        'model.comment_mode': function (value, oldValue) {
            this.changeTextMode(value, oldValue, 'comment');
        },
    },
    created() {
        this.setUpOperatorWatchers();
    },
    methods: {
        changeTextMode(value, oldValue, modelName) {
            if (
                value == null ||
                this.model[modelName] == null ||
                JSON.stringify(value) === JSON.stringify(oldValue)
            ) {
                return;
            }
            this.model[modelName] = changeMode(oldValue[0], value[0], this.model[modelName]);
        },
        setUpOperatorWatchers() {
            for (const fieldName of Object.keys(this.model)) {
                if (fieldName.endsWith('_op')) {
                    const parentFieldName = fieldName.substring(0, fieldName.length - 3)
                    this.$watch(
                        `model.${parentFieldName}`,
                        (newValue) => {
                            if (newValue != null && newValue.length === 1 && this.model[fieldName] === 'and') {
                                this.model[fieldName] = 'or';
                            }
                        },
                    );
                }
            }
        },
        constructFilterValues() {
            const result = {};
            if (this.model != null) {
                for (const fieldName of Object.keys(this.model)) {
                    if (this.fields[fieldName] != null && this.fields[fieldName].type === 'multiselectClear') {
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
                    } else if (`${fieldName}_mode` in this.model) {
                        const modeField = `${fieldName}_mode`;
                        if (this.model[modeField] !== undefined && this.model[modeField][0] === 'betacode') {
                            result[fieldName] = changeMode('betacode', 'greek', this.model[fieldName].trim());
                        } else {
                            result[fieldName] = this.model[fieldName].trim();
                        }
                    } else {
                        const value = this.model[fieldName];
                        // the label-checkboxes return a list with one element inside
                        if (Array.isArray(value)) {
                            const [v] = value;
                            result[fieldName] = v;
                        } else {
                            result[fieldName] = this.model[fieldName];
                        }
                    }
                }
            }
            return result;
        },
        modelUpdated(value, fieldName) {
            this.lastChangedField = fieldName;
        },
        handleInvalidState() {
            let revalidate = false;
            if ('year_from' in this.model && Number.isNaN(this.model.year_from)) {
                delete this.model.year_from;
                revalidate = true;
            }
            if ('year_to' in this.model && Number.isNaN(this.model.year_to)) {
                delete this.model.year_to;
                revalidate = true;
            }
            if (revalidate) {
                this.$refs.form.validate();
                return;
            }
            this.cancelPendingInputRequest();
        },

        cancelPendingInputRequest() {
            if (this.inputCancel !== null) {
                window.clearTimeout(this.inputCancel);
                this.inputCancel = null;
            }
        },
        adjustYearBoundaries() {
            const { year_from, year_to } = this.model;

            if ('year_from' in this.fields && 'year_to' in this.fields) {
                this.fields.year_to.min = year_from != null ? Math.max(YEAR_MIN, year_from) : YEAR_MIN;
                this.fields.year_from.max = year_to != null ? Math.min(YEAR_MAX, year_to) : YEAR_MAX;
            }
        },
        onValidated(isValid) {
            // do nothin but cancelling requests if invalid
            if (!isValid) {
                this.handleInvalidState();
                return;
            }

            if (this.model != null) {
                for (const fieldName of Object.keys(this.model)) {
                    if (
                        this.model[fieldName] == null
                        || this.model[fieldName] === ''
                    ) {
                        delete this.model[fieldName];
                    }
                    const field = this.fields[fieldName];
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        delete this.model[fieldName];
                    }
                }
            }

            this.adjustYearBoundaries();

            // Cancel timeouts caused by input requests not long ago
            this.cancelPendingInputRequest();

            // Send requests to update filters and result table
            // Add a delay to requests originated from input field changes to limit the number of requests
            let timeoutValue = 0;
            if (this.lastChangedField !== '' && this.fields[this.lastChangedField].type === 'input') {
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
            if (a.id === -1) return -1;
            if (b.id === -1) return 1;

            // Place 'true' before 'false'
            if (a.name === 'true' && b.name === 'false') return -1;
            if (a.name === 'false' && b.name === 'true') return 1;

            const isString = (val) => typeof val === 'string' || val instanceof String;

            if (isString(a.name) && isString(b.name)) {
                const compareByRegex = (regex, parseFn = null, fallback = 0) => {
                    const first = a.name.match(regex);
                    const second = b.name.match(regex);
                    if (!first || !second) return fallback;

                    const valuesA = parseFn ? parseFn(first) : first.slice(1);
                    const valuesB = parseFn ? parseFn(second) : second.slice(1);

                    for (let i = 0; i < Math.min(valuesA.length, valuesB.length); i++) {
                        if (valuesA[i] < valuesB[i]) return -1;
                        if (valuesA[i] > valuesB[i]) return 1;
                    }

                    return 0;
                };

                // 1. Numeric (e.g., 571A)
                let result = compareByRegex(this.numRegex, match => [parseInt(match[1], 10)]);
                if (result !== 0) return result;

                // 2. RGK (e.g., II.513)
                result = compareByRegex(this.rgkRegex, match => [match[1], parseInt(match[2], 10)]);
                if (result !== 0) return result;

                // 3. VGH (e.g., 513.B)
                const vghA = a.name.match(this.vghRegex);
                const vghB = b.name.match(this.vghRegex);
                if (vghA || vghB) {
                    if (!vghA) return 1;
                    if (!vghB) return -1;

                    const valA = [parseInt(vghA[1], 10), vghA[2]];
                    const valB = [parseInt(vghB[1], 10), vghB[2]];

                    if (valA[0] !== valB[0]) return valA[0] - valB[0];
                    if (valA[1] < valB[1]) return -1;
                    if (valA[1] > valB[1]) return 1;
                    return 0;
                }

                // 4. Role with count (e.g., Patron (7))
                result = compareByRegex(this.roleCountRegex, match => [parseInt(match[1], 10)], 0);
                if (result !== 0) return -result; // descending order

                // 5. Greek
                const greekA = a.name.match(this.greekRegex);
                const greekB = b.name.match(this.greekRegex);
                if (greekA && greekB) {
                    const cleanA = removeGreekAccents(a.name);
                    const cleanB = removeGreekAccents(b.name);
                    if (cleanA < cleanB) return -1;
                    if (cleanA > cleanB) return 1;
                    return 0;
                }

                // 6. AlphaNumRest (e.g., Γ 5 (Eustratiades 245))
                result = compareByRegex(this.alphaNumRestRegex, match => [
                    match[1], parseInt(match[2], 10), match[3]
                ]);
                if (result !== 0) return result;
            }

            // Default string comparison
            if (a.name < b.name) return -1;
            if (a.name > b.name) return 1;
            return 0;
        },

        resetAllFilters() {
            this.model = JSON.parse(JSON.stringify(this.originalModel));
            this.onValidated(true);
        },
        onLoaded() {
            if (!this.initialized) {
                this.init(true);
                this.initialized = true;
            }
            if (this.historyRequest) {
                this.init(this.historyRequest === 'init');
                this.historyRequest = false;
            }

            // Update aggregation fields
            for (const fieldName of Object.keys(this.fields)) {
                const field = this.fields[fieldName];
                if (field.type === 'multiselectClear') {
                    field.values = this.aggregation[fieldName] == null
                        ? []
                        : this.aggregation[fieldName].sort(this.sortByName);
                    field.originalValues = JSON.parse(JSON.stringify(field.values));
                    if (field.dependency != null && this.model[field.dependency] == null) {
                        dependencyField(field, this.model);
                    } else {
                        enableField(field, null, true);
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
            // Deep clone data to avoid mutations
            const filteredData = JSON.parse(JSON.stringify(data));

            const isDefaultLimit = (obj) => obj.limit === 25;
            const isDefaultPage = (obj) => obj.page === 1;
            const isDefaultOrdering = (obj) =>
                obj.orderBy === this.tableOptions.orderBy.column && obj.ascending === 1;

            // Remove default pagination and ordering parameters
            if ('limit' in filteredData && isDefaultLimit(filteredData)) {
                delete filteredData.limit;
            }
            if ('page' in filteredData && isDefaultPage(filteredData)) {
                delete filteredData.page;
            }
            if (
                'orderBy' in filteredData
                && 'ascending' in filteredData
                && isDefaultOrdering(filteredData)
            ) {
                delete filteredData.orderBy;
                delete filteredData.ascending;
            }

            // Clean filters by removing default or invalid filter values
            if ('filters' in filteredData) {
                for (const fieldName of Object.keys(this.fields)) {
                    if (!(fieldName in filteredData.filters)) continue;

                    const field = this.fields[fieldName];
                    const filterValue = filteredData.filters[fieldName];

                    // Remove filter if value equals the original model's value
                    if (
                        fieldName in this.originalModel &&
                        this.model[fieldName] === this.originalModel[fieldName]
                    ) {
                        delete filteredData.filters[fieldName];
                        continue;
                    }

                    // Remove filter if multiDependency is not satisfied
                    if (
                        field.multiDependency != null &&
                        (
                            this.model[field.multiDependency] == null ||
                            this.model[field.multiDependency].length < 2
                        )
                    ) {
                        delete filteredData.filters[fieldName];
                    }
                }
            }

            // Push filtered state to history with updated URL query string
            const baseUrl = document.location.href.split('?')[0];
            const queryString = qs.stringify(filteredData);

            window.history.pushState(filteredData, document.title, `${baseUrl}?${queryString}`);
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
            // Parse URL parameters after '?'
            const queryString = window.location.href.split('?', 2)[1] || '';
            const params = qs.parse(queryString);

            // Deep clone original model
            const model = JSON.parse(JSON.stringify(this.originalModel));

            // Helper: check if a string is non-empty
            const isNonEmptyString = (str) => typeof str === 'string' && str.trim() !== '';

            // Process filters from params
            if ('filters' in params) {
                for (const key of Object.keys(params.filters)) {
                    const value = params.filters[key];

                    if (key === 'date') {
                        if ('from' in value) model.year_from = Number(value.from);
                        if ('to' in value) model.year_to = Number(value.to);
                        continue;
                    }

                    if (!(key in this.fields)) continue;

                    const field = this.fields[key];

                    if (field.type === 'multiselectClear' && this.data.aggregation[key] != null) {
                        if (Array.isArray(value)) {
                            model[key] = this.data.aggregation[key].filter((v) => value.includes(String(v.id)));
                        } else {
                            [model[key]] = this.data.aggregation[key].filter((v) => String(v.id) === value);
                        }
                        continue;
                    }

                    if (key.endsWith('_mode')) {
                        // _mode watcher will handle conversion
                        model[key] = [value];
                        continue;
                    }

                    model[key] = value;
                }
            }

            this.model = model;
            this.oldFilterValues = this.constructFilterValues();

            // Handle pagination
            if ('page' in params) {
                this.actualRequest = false;
                this.$refs.resultTable.setPage(params.page);
            }

            // Handle ordering
            this.actualRequest = false;

            if ('orderBy' in params) {
                const ascending = 'ascending' in params ? params.ascending : true;
                this.$refs.resultTable.setOrder(params.orderBy, ascending);
            } else if (
                'filters' in params &&
                (isNonEmptyString(params.filters.text) || isNonEmptyString(params.filters.comment))
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
            this.updateManagements({
                action: 'add',
                target: 'selection',
                managementCollections,
            });
        },
        removeManagementsFromSelection(managementCollections) {
            this.updateManagements({
                action: 'remove',
                target: 'selection',
                managementCollections,
            });
        },
        addManagementsToResults(managementCollections) {
            this.updateManagements({
                action: 'add',
                target: 'results',
                managementCollections,
            });
        },
        removeManagementsFromResults(managementCollections) {
            this.updateManagements({
                action: 'remove',
                target: 'results',
                managementCollections,
            });
        },
        updateManagements({ action, target, managementCollections }) {
            const urlMap = {
                add: this.urls.managements_add,
                remove: this.urls.managements_remove,
            };

            const messages = {
                add: {
                    success: 'Management collections added successfully.',
                    error: 'Something went wrong while adding the management collections.',
                },
                remove: {
                    success: 'Management collections removed successfully.',
                    error: 'Something went wrong while removing the management collections.',
                },
            };

            const payload =
                target === 'selection'
                    ? { ids: this.collectionArray, managements: managementCollections }
                    : { filter: this.constructFilterValues(), managements: managementCollections };

            this.openRequests += 1;

            window.axios
                .put(urlMap[action], payload)
                .then(() => {
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.alerts.push({
                        type: 'success',
                        message: messages[action].success,
                    });
                })
                .catch((error) => {
                    this.alerts.push({
                        type: 'error',
                        message: messages[action].error,
                    });
                    console.error(error);
                })
                .finally(() => {
                    this.openRequests -= 1;
                });
        },


        /**
         * Remove active filter
         * @param {String} key The key for which filter needs to be removed
         * @param {Number} valueIndex -1 == switch || -2 == string || rest == remove index from array
         */
        deleteActiveFilter({ key, valueIndex }) {
            if (key === 'year_from' || key === 'year_to') {
                this.model[key] = undefined;
            } else if (valueIndex === -1) {
                this.model[key] = 'or';
            } else if (valueIndex === -2) {
                this.model[key] = '';
            } else {
                this.model[key].splice(valueIndex, 1);
            }
            this.lastChangedField = '';
            this.onValidated(true);
        },
        addActiveFilter(key) {
            const show = [];
            const load = this.fields[key];
            const currentModel = load.model;
            const modelValue = this.model[currentModel];
            const filterLabel = load.label;
            if (modelValue !== undefined
                && currentModel !== 'text_combination'
                && currentModel !== 'text_fields'
                && currentModel !== 'date_search_type'
                && currentModel !== 'title_type'
                && !currentModel.endsWith('_mode')) {
                if (currentModel.endsWith('_op')) {
                    if (modelValue !== 'or') {
                        show.push({
                            key: currentModel,
                            value: [{ name: '' }],
                            label: load.switchLabel,
                            type: 'switch',
                        });
                    }
                } else if (Array.isArray(modelValue)) {
                    if (modelValue.length) {
                        show.push({
                            key: currentModel,
                            value: modelValue,
                            label: filterLabel,
                            type: 'array',
                        });
                    }
                } else if (`${key}_mode` in this.model) {
                    const languageMode = this.model[`${key}_mode`][0];
                    show.push({
                        key: currentModel,
                        value: [{ name: modelValue }],
                        label: filterLabel,
                        type: typeof modelValue,
                        mode: languageMode,
                    });
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
            return show;
        },
    },
    async requestFunction(data) {
        const params = { ...data };
        const searchApp = this.$parent.$parent;

        delete params.query;
        delete params.byColumn;
        if (!('orderBy' in params)) {
            delete params.ascending;
        }

        params.filters = searchApp.constructFilterValues();
        if (!params.filters) {
            delete params.filters;
        }

        searchApp.openRequests += 1;
        const handleError = (error) => {
            searchApp.openRequests -= 1;
            searchApp.alerts.push({
                type: 'error',
                message:
                    'Something went wrong while processing your request. Please verify your input is valid.',
            });
            console.error(error);
            return {
                data: {
                    data: this.data,
                    count: this.count,
                },
            };
        };

        const axiosGet = async (url, options = {}) => {
            if (searchApp.openRequests > 1 && searchApp.tableCancel != null) {
                searchApp.tableCancel('Operation canceled by newer request');
            }

            try {
                const response = await window.axios.get(url, {
                    cancelToken: new window.axios.CancelToken((c) => {
                        searchApp.tableCancel = c;
                    }),
                    ...options,
                });
                searchApp.alerts = [];
                searchApp.onData(response.data);
                return response;
            } catch (error) {
                if (window.axios.isCancel(error)) {
                    return {
                        data: {
                            data: this.data,
                            count: this.count,
                        },
                    };
                }
                return handleError(error);
            }
        };

        if (!searchApp.initialized) {
            searchApp.onData(searchApp.data);
            return {
                data: {
                    data: searchApp.data.data,
                    count: searchApp.data.count,
                },
            };
        }

        if (!searchApp.actualRequest) {
            return {
                data: {
                    data: this.data,
                    count: this.count,
                },
            };
        }

        if (searchApp.historyRequest) {
            let url = this.url;
            if (searchApp.historyRequest !== 'init') {
                url = `${url}?${searchApp.historyRequest}`;
            }
            return await axiosGet(url);
        }

        if (!searchApp.noHistory) {
            searchApp.pushHistory(params);
        } else {
            searchApp.noHistory = false;
        }

        return await axiosGet(this.url, {
            params,
            paramsSerializer: qs.stringify,
        });
    },

    YEAR_MIN,
    YEAR_MAX,
};
