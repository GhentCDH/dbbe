window.axios = require('axios')

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import * as uiv from 'uiv'

import fieldMultiselectClear from '../FormFields/fieldMultiselectClear'
import Alerts from '../Alerts'
import Panel from './Panel'

const modalComponents = require.context('./Modals', false, /[.]vue$/)

Vue.use(VueFormGenerator)
Vue.use(uiv)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('alerts', Alerts)

for(let key of modalComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    if (['Invalid', 'Reset', 'Save'].includes(compName)) {
        Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Modal', modalComponents(key).default)
    }
}

export default {
    props: {
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
        initRoles: {
            type: String,
            default: '',
        },
        initContributorRoles: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            urls: JSON.parse(this.initUrls),
            data: JSON.parse(this.initData),
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            openRequests: 0,
            alerts: [],
            saveAlerts: [],
            originalModel: {},
            diff: [],
            resetModal: false,
            invalidModal: false,
            saveModal: false,
            invalidPanels: false,
            scrollY: null,
            isSticky: false,
            stickyStyle: {},
            reloads: [],
        }
    },
    watch: {
        scrollY() {
            let anchor = this.$refs.anchor.getBoundingClientRect()
            if (anchor.top < 30) {
                this.isSticky = true
                this.stickyStyle = {
                    width: anchor.width + 'px',
                }
            }
            else {
                this.isSticky = false
                this.stickyStyle = {}
            }
        },
    },
    mounted () {
        this.initScroll();

        this.setData();
        this.originalModel = JSON.parse(JSON.stringify(this.model));

        // Initialize panels after model is updated
        this.$nextTick(() => {
            if (!this.data.clone) {
                for (let panel of this.panels) {
                    this.$refs[panel].init();
                }
            }
        });

        // Load some (slower) data asynchronously
        this.loadAsync();
    },
    methods: {
        initScroll() {
            window.addEventListener('scroll', () => {
                this.scrollY = Math.round(window.scrollY);
            });
        },
        loadAsync() {},
        validateForms() {
            for (let panel of this.panels) {
                this.$refs[panel].validate();
            }
        },
        calcAllChanges() {
            for (let panel of this.panels) {
                this.$refs[panel].calcChanges();
            }
        },
        validated(isValid, errors) {
            this.invalidPanels = false;
            for (let panel of this.panels) {
                if (!this.$refs[panel].isValid) {
                    this.invalidPanels = true;
                    break;
                }
            }

            this.calcDiff();
        },
        calcDiff() {
            this.diff = []
            for (let panel of this.panels) {
                this.diff = this.diff.concat(this.$refs[panel].changes);
            }

            if (this.diff.length !== 0) {
                window.onbeforeunload = function(e) {
                    let dialogText = 'There are unsaved changes.';
                    e.returnValue = dialogText;
                    return dialogText;
                }
            }
        },
        toSave() {
            let result = {}
            for (let diff of this.diff) {
                if ('keyGroup' in diff) {
                    if (!(diff.keyGroup in result)) {
                        result[diff.keyGroup] = {}
                    }
                    result[diff.keyGroup][diff.key] = diff.value
                }
                else {
                    result[diff.key] = diff.value
                }
            }
            return result
        },
        reset() {
            this.resetModal = false
            this.model = JSON.parse(JSON.stringify(this.originalModel))
            Vue.nextTick(() => {this.validateForms()})
        },
        saveButton() {
            this.validateForms()
            if (this.invalidPanels) {
                this.invalidModal = true
            }
            else {
                this.saveModal = true
            }
        },
        cancelSave() {
            this.saveModal = false
            this.saveAlerts = []
        },
        isLoginError(error) {
            return error.message === 'Network Error'
        },
        getErrorMessage(error) {
            if (error && error.response && error.response.data && error.response.data.error && error.response.data.error.message) {
                return error.response.data.error.message

            }
            return null
        },
        reloadSimpleItems(type) {
            this.reloadItems(
                type,
                [type],
                [this[type]],
                this.urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
            );
        },
        // parent can either be an array of multiple parents or a single parent
        reloadNestedItems(type, parent) {
            this.reloadItems(
                type,
                [type],
                Array.isArray(parent) ? parent.map(p => p[type]) : [parent[type]],
                this.urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
            );
        },
        reloadItems(type, keys, items, url, filters) {
            // Be careful to mutate the existing array and not create a new one
            for (let panel of this.panels) {
                this.$refs[panel].disableFields(keys);
            }
            this.reloads.push(type);
            axios.get(url)
                .then( (response) => {
                    for (let i = 0; i < items.length; i++) {
                        let data = [];
                        if (filters == null || filters[i] == null) {
                            // Copy data
                            data = response.data.filter(() => true);
                        } else {
                            data = response.data.filter(filters[i]);
                        }
                        while (items[i].length) {
                            items[i].splice(0, 1);
                        }
                        while (data.length) {
                            items[i].push(data.shift());
                        }
                    }
                    for (let panel of this.panels) {
                        this.$refs[panel].enableFields(keys);
                    }
                    let typeIndex = this.reloads.indexOf(type);
                    if (typeIndex > -1) {
                        this.reloads.splice(typeIndex, 1);
                    }
                })
                .catch( (error) => {
                    this.alerts.push({type: 'error', message: 'Something went wrong while loading data.', login: this.isLoginError(error)});
                    this.$notify({
                        placement: 'top-left',
                        type: 'danger',
                        title: 'Oh snap!',
                        content: this.alerts[this.alerts.length - 1].message,
                        duration: 0,
                    });
                    console.log(error);
                })
        },
    },
}
