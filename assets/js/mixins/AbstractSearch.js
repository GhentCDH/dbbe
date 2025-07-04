import qs from 'qs';

import Vue from 'vue';
import {dependencyField, enableField,removeGreekAccents} from "../helpers/formFieldUtils";
import VueFormGenerator from 'vue-form-generator';
import VueMultiselect from 'vue-multiselect';
import VueTables from 'vue-tables-2';
import * as uiv from 'uiv';

import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear.vue';
import Delete from '../Components/Edit/Modals/Delete.vue';
import CollectionManager from '../Components/Search/CollectionManager.vue';
import fieldCheckboxes from '../Components/FormFields/fieldCheckboxes.vue';

import { YEAR_MIN, YEAR_MAX, changeMode } from '../helpers/formatUtil';
import axios from 'axios';
Vue.use(uiv);
Vue.use(VueFormGenerator);
Vue.use(VueTables.ServerTable);

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
            const addField = (field) => {
                if (!field.multiple || field.multi === true) {
                    res[field.model] = field;
                }
            };

            if (this.schema) {
                if (this.schema.fields) {
                    Object.values(this.schema.fields).forEach(addField);
                }
                if (this.schema.groups) {
                    this.schema.groups.forEach(group => {
                        if (group.fields) {
                            group.fields.forEach(field => {
                                if (!this.multiple || field.multi === true) {
                                    res[field.model] = field;
                                }
                            });
                        }
                    });
                }
            }

            return res;
        },
        notEmptyFields() {
            const show = [];
            const collectFilters = (field) => {
                show.push(...this.addActiveFilter(field.model || field));
            };

            if (this.schema) {
                if (this.schema.fields) {
                    Object.values(this.schema.fields).forEach(field => collectFilters(field));
                }
                if (this.schema.groups) {
                    this.schema.groups.forEach(group => {
                        if (group.fields) {
                            group.fields.forEach(field => collectFilters(field));
                        }
                    });
                }
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
            if (this.model == null) return result;
            for (const fieldName of Object.keys(this.model)) {
                const fieldValue = this.model[fieldName];
                const fieldDef = this.fields[fieldName];
                if (fieldDef?.type === 'multiselectClear' && fieldValue != null) {
                    if (Array.isArray(fieldValue)) {
                        result[fieldName] = fieldValue.map(v => v.id);
                    } else {
                        result[fieldName] = fieldValue.id;
                    }
                    continue;
                }
                if (fieldName === 'year_from' || fieldName === 'year_to') {
                    if (!result.date) result.date = {};
                    result.date[fieldName === 'year_from' ? 'from' : 'to'] = fieldValue;
                    continue;
                }

                const modeField = `${fieldName}_mode`;
                if (modeField in this.model) {
                    if (this.model[modeField]?.[0] === 'betacode') {
                        result[fieldName] = changeMode('betacode', 'greek', fieldValue.trim());
                    } else {
                        result[fieldName] = fieldValue.trim();
                    }
                    continue;
                }
                if (Array.isArray(fieldValue)) {
                    result[fieldName] = fieldValue[0];
                } else {
                    result[fieldName] = fieldValue;
                }
            }

            return result;
        },
        modelUpdated(value, fieldName) {
            this.lastChangedField = fieldName;
        },
        onValidated(isValid) {
            const clearInvalidYearFields = () => {
                let revalidateNeeded = false;
                ['year_from', 'year_to'].forEach(field => {
                    if (field in this.model && Number.isNaN(this.model[field])) {
                        delete this.model[field];
                        revalidateNeeded = true;
                    }
                });
                return revalidateNeeded;
            };

            const clearEmptyOrDependentFields = () => {
                if (!this.model) return;
                for (const [fieldName, value] of Object.entries(this.model)) {
                    if (value == null || value === '') {
                        delete this.model[fieldName];
                        continue;
                    }
                    const field = this.fields[fieldName];
                    if (field?.dependency && this.model[field.dependency] == null) {
                        delete this.model[fieldName];
                    }
                }
            };

            const updateYearBounds = () => {
                if ('year_from' in this.fields && 'year_to' in this.fields) {
                    this.fields.year_to.min = this.model.year_from != null
                        ? Math.max(YEAR_MIN, this.model.year_from)
                        : YEAR_MIN;
                    this.fields.year_from.max = this.model.year_to != null
                        ? Math.min(YEAR_MAX, this.model.year_to)
                        : YEAR_MAX;
                }
            };

            const clearPendingTimeout = () => {
                if (this.inputCancel !== null) {
                    clearTimeout(this.inputCancel);
                    this.inputCancel = null;
                }
            };

            if (!isValid) {
                if (clearInvalidYearFields()) {
                    this.$refs.form.validate();
                    return;
                }
                clearPendingTimeout();
                return;
            }

            clearEmptyOrDependentFields();
            updateYearBounds();
            clearPendingTimeout();

            const isInputField = this.lastChangedField && this.fields[this.lastChangedField]?.type === 'input';
            const timeoutValue = isInputField ? 1000 : 0;

            if (this.lastChangedField === 'text' || this.lastChangedField === 'comment') {
                this.actualRequest = false;
                const lastValue = this.model[this.lastChangedField];
                if (lastValue == null || lastValue === '') {
                    if (this.lastOrder == null) {
                        this.$refs.resultTable.setOrder(this.defaultOrdering, true);
                    } else {
                        const asc = this.lastOrder.ascending ?? false;
                        this.$refs.resultTable.setOrder(this.lastOrder.column, asc);
                    }
                } else {
                    this.lastOrder = JSON.parse(JSON.stringify(this.$refs.resultTable.options.orderBy));
                    this.$refs.resultTable.setOrder(null);
                }
            }

            if (this.lastChangedField === 'text_type') {
                if (!this.model.text) {
                    this.actualRequest = false;
                } else {
                    this.actualRequest = false;
                    this.$refs.resultTable.setOrder(null);
                    this.actualRequest = true;
                }
            } else {
                this.actualRequest = true;
            }

            if (this.historyRequest) {
                this.actualRequest = false;
            }

            this.inputCancel = setTimeout(() => {
                this.inputCancel = null;
                const filterValues = this.constructFilterValues();

                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues;
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues);
                }
            }, timeoutValue);
        },
        sortByName(a, b) {
            // Helper to parse integer safely
            const parseIntSafe = (str) => parseInt(str, 10);

            // Helper to compare numbers
            const compareNumbers = (x, y) => (x < y ? -1 : x > y ? 1 : 0);

            // Handle special id cases
            if (a.id === -1) return -1;
            if (b.id === -1) return 1;

            // Handle specific string cases 'false' and 'true'
            if (a.name === 'false' && b.name === 'true') return 1;
            if (a.name === 'true' && b.name === 'false') return -1;

            // Ensure both names are strings
            if (
                (typeof a.name === 'string' || a.name instanceof String) &&
                (typeof b.name === 'string' || b.name instanceof String)
            ) {
                // Numeric regex comparison
                let firstMatch = a.name.match(this.numRegex);
                let secondMatch = b.name.match(this.numRegex);
                if (firstMatch && secondMatch) {
                    const firstNum = parseIntSafe(firstMatch[1]);
                    const secondNum = parseIntSafe(secondMatch[1]);
                    const cmp = compareNumbers(firstNum, secondNum);
                    if (cmp !== 0) return cmp;
                }

                // RGK regex comparison
                firstMatch = a.name.match(this.rgkRegex);
                secondMatch = b.name.match(this.rgkRegex);
                if (firstMatch && secondMatch) {
                    let cmp = compareNumbers(firstMatch[1], secondMatch[1]);
                    if (cmp !== 0) return cmp;
                    return compareNumbers(firstMatch[2], secondMatch[2]);
                }

                // VGH regex comparison
                firstMatch = a.name.match(this.vghRegex);
                secondMatch = b.name.match(this.vghRegex);
                if (firstMatch || secondMatch) {
                    if (!firstMatch) return 1;  // Irregular vghs go at the end
                    if (!secondMatch) return -1;
                    let cmp = compareNumbers(firstMatch[1], secondMatch[1]);
                    if (cmp !== 0) return cmp;
                    return compareNumbers(firstMatch[2], secondMatch[2]);
                }

                // Role with count regex comparison
                firstMatch = a.name.match(this.roleCountRegex);
                secondMatch = b.name.match(this.roleCountRegex);
                if (firstMatch && secondMatch) {
                    // Note: reverse numeric order
                    return parseIntSafe(secondMatch[1]) - parseIntSafe(firstMatch[1]);
                }

                // Greek regex comparison
                firstMatch = a.name.match(this.greekRegex);
                secondMatch = b.name.match(this.greekRegex);
                if (firstMatch && secondMatch) {
                    const aName = removeGreekAccents(a.name);
                    const bName = removeGreekAccents(b.name);
                    if (aName < bName) return -1;
                    if (aName > bName) return 1;
                    return 0;
                }

                // AlphaNumRest regex comparison
                firstMatch = a.name.match(this.alphaNumRestRegex);
                secondMatch = b.name.match(this.alphaNumRestRegex);
                if (firstMatch && secondMatch) {
                    let cmp = compareNumbers(firstMatch[1], secondMatch[1]);
                    if (cmp !== 0) return cmp;

                    cmp = compareNumbers(firstMatch[2], secondMatch[2]);
                    if (cmp !== 0) return cmp;

                    return compareNumbers(firstMatch[3], secondMatch[3]);
                }
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
                for (const fieldName of Object.keys(this.fields)) {
                    if (fieldName in filteredData.filters) {
                        const field = this.fields[fieldName];
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
                    } else if (key in this.fields) {
                        if (
                            this.fields[key].type === 'multiselectClear'
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
                        } else if (key.endsWith('_mode')) {
                            // do nothing else special, conversion will hapen in _mode watcher
                            model[key] = [params.filters[key]];
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

            axios
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
            const field = this.fields[key];
            const currentKey = field.model;
            const value = this.model[currentKey];
            const label = field.label;

            const isIgnored =
                currentKey === 'text_combination' ||
                currentKey === 'text_fields' ||
                currentKey === 'date_search_type' ||
                currentKey === 'title_type' ||
                currentKey.endsWith('_mode');

            if (value === undefined || isIgnored) return show;

            if (currentKey.endsWith('_op')) {
                if (value !== 'or') {
                    show.push({
                        key: currentKey,
                        value: [{ name: '' }],
                        label: field.switchLabel,
                        type: 'switch',
                    });
                }
            } else if (Array.isArray(value)) {
                if (value.length) {
                    show.push({
                        key: currentKey,
                        value,
                        label,
                        type: 'array',
                    });
                }
            } else {
                const mode = this.model[`${key}_mode`]?.[0];
                const normalizedValue = value.name !== undefined ? [value] : [{ name: value }];

                show.push({
                    key: currentKey,
                    value: normalizedValue,
                    label,
                    type: typeof value,
                    ...(mode ? { mode } : {}),
                });
            }

            return show;
        }
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
                const response = await axios.get(url, {
                    cancelToken: new axios.CancelToken((c) => {
                        searchApp.tableCancel = c;
                    }),
                    ...options,
                });
                searchApp.alerts = [];
                searchApp.onData(response.data);
                return response;
            } catch (error) {
                if (axios.isCancel(error)) {
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
