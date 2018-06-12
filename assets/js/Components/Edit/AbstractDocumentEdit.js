window.axios = require('axios')

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import * as uiv from 'uiv'
import VueMultiselect from 'vue-multiselect'

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
            originalModel: {},
            diff:[],
            resetModal: false,
            invalidModal: false,
            saveModal: false,
            invalidForms: false,
            scrollY: null,
            isSticky: false,
            stickyStyle: {},
        }
    },
    watch: {
        scrollY(newValue) {
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
    methods: {
        validateForms() {
            for (let form of this.forms) {
                this.$refs[form].validate()
            }
        },
        validated(isValid, errors) {
            this.invalidForms = false;
            for (let form of this.forms) {
                if (!this.$refs[form].isValid) {
                    this.invalidForms = true;
                    break;
                }
            }

            this.calcDiff()
        },
        calcDiff() {
            this.diff = []
            for (let form of this.forms) {
                this.diff = this.diff.concat(this.$refs[form].changes)
            }

            if (this.diff.length !== 0) {
                window.onbeforeunload = function(e) {
                    let dialogText = 'There are unsaved changes.'
                    e.returnValue = dialogText
                    return dialogText
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
            if (this.invalidForms) {
                this.invalidModal = true
            }
            else {
                this.saveModal = true
            }
        },
        reload() {
            window.onbeforeunload = function () {}
            window.location.reload(true)
        },
    }
}
