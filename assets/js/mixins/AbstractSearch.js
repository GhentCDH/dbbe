import qs from 'qs';

import Vue from 'vue';
import {dependencyField, enableField} from "../helpers/formFieldUtils";
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
import {sortByName} from "@/helpers/abstractSearchHelpers/sortUtil";
import {constructFilterValues} from "@/helpers/abstractSearchHelpers/filterUtil";
import {popHistory, pushHistory} from "@/helpers/abstractSearchHelpers/historyUtil";
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
            noHistory: false,
            lastChangedField: '',
            inputCancel: null,
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

            if (!this.schema) return show;
            if (this.schema.fields) {
                Object.values(this.schema.fields).forEach(collectFilters);
            }

            if (this.schema.groups) {
                this.schema.groups.forEach(group => {
                    (group.fields || []).forEach(collectFilters);
                });
            }

            return show;
        },
    },

    mounted() {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        window.onpopstate = (event) => {
            const historyRequest = popHistory();
            this.historyRequest = historyRequest;
            this.$refs.resultTable.refresh();
        };
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
                const filterValues = constructFilterValues(this.model, this.fields);

                if (JSON.stringify(filterValues) !== JSON.stringify(this.oldFilterValues)) {
                    this.oldFilterValues = filterValues;
                    VueTables.Event.$emit('vue-tables.filter::filters', filterValues);
                }
            }, timeoutValue);
        },

        resetAllFilters() {
            this.model = JSON.parse(JSON.stringify(this.originalModel));
            this.onValidated(true);
        },
        onDataExtend(data) {
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

            // Check whether lemma column should be displayed
            this.lemmaSearch = false;
            for (const item of data.data) {
                if (
                    'lemma_text' in item
                ) {
                    this.lemmaSearch = true;
                    break;
                }
            }
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

            for (const fieldName of Object.keys(this.fields)) {
                const field = this.fields[fieldName];
                if (field.type === 'multiselectClear') {
                    field.values = this.aggregation[fieldName] == null
                        ? []
                        : this.aggregation[fieldName].sort(sortByName);
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

            this.updateCountRecords();

            this.openRequests -= 1;
        },

        init() {
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
            this.oldFilterValues = constructFilterValues(this.model, this.fields);
            if ('page' in params) {
                this.actualRequest = false;
                this.$refs.resultTable.setPage(params.page);
            }
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

        params.filters = constructFilterValues(searchApp.model, searchApp.fields);
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
            pushHistory(params, searchApp.model, searchApp.originalModel, searchApp.fields, searchApp.tableOptions);
        } else {
            searchApp.noHistory = false;
        }

        return await axiosGet(this.url, {
            params,
            paramsSerializer: qs.stringify,
        });
    },
};