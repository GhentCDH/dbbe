<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="form"
            @validated="validated" />
        <div
            v-if="warnEstimate"
            class="small text-warning">
            <p>When indicating an estimate, please add 1 year to the start year to prevent overlap. Examples:</p>
            <ul>
                <li>1301-1400</li>
                <li>1476-1500</li>
            </ul>
        </div>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

var YEAR_MIN = 1
var YEAR_MAX = (new Date()).getFullYear()

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        keyGroup: {
            type: String,
            default: 'date',
        },
        groupLabel: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            schema: {
                fields: {
                    exactDate: {
                        type: 'checkbox',
                        label: 'Exact date',
                        labelClasses: 'control-label',
                        model: 'exactDate',
                        default: false,

                    },
                    exactYear: {
                        type: 'checkbox',
                        label: 'Exact year',
                        labelClasses: 'control-label',
                        model: 'exactYear',
                        default: false,
                    },
                    floorYear: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        labelClasses: 'control-label',
                        model: 'floorYear',
                        required: this.floorDayMonth != null,
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: [VueFormGenerator.validators.number, VueFormGenerator.validators.required],
                    },
                    floorDayMonth: {
                        type: 'input',
                        inputType: 'string',
                        label: 'Day from',
                        labelClasses: 'control-label',
                        model: 'floorDayMonth',
                        validator: [VueFormGenerator.validators.regexp],
                        pattern: '^\\d{2}[/]\\d{2}$',
                        help: 'Please use the format "DD/MM", e.g. 24/03.',
                    },
                    ceilingYear: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        labelClasses: 'control-label',
                        model: 'ceilingYear',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    ceilingDayMonth: {
                        type: 'input',
                        inputType: 'string',
                        label: 'Day to',
                        labelClasses: 'control-label',
                        model: 'ceilingDayMonth',
                        required: this.ceilingYear != null,
                        validator: [VueFormGenerator.validators.regexp, VueFormGenerator.validators.required],
                        pattern: '^\\d{2}[/]\\d{2}$',
                        help: 'Please use the format "DD/MM", e.g. 24/03.',
                    },
                }
            }
        }
    },
    computed: {
        warnEstimate: function() {
            if (this.model == null || this.model.floorYear == null || this.model.ceilingYear == null) {
                return false
            }
            if (this.model.floorYear !== this.model.ceilingYear && this.model.floorYear % 25 === 0 && this.model.ceilingYear % 25 === 0) {
                return true
            }
            return false
        }
    },
    watch: {
        'model.exactDate': function (newValue, oldValue) {
            if (this.model.exactDate == null) {
                return
            }

            // If the date is exact, then the year is exact as well
            if (this.model.exactDate) {
                this.model.exactYear = true
            }

            // Year will be handled by exactYear
            this.schema.fields.ceilingDayMonth.disabled = this.model.exactDate
            if (this.model.floorDayMonth == null && this.model.ceilingDayMonth != null) {
                this.model.floorDayMonth = this.model.ceilingDayMonth
            }
            else {
                this.model.ceilingDayMonth = this.model.floorDayMonth
            }
        },
        'model.exactYear': function (newValue, oldValue) {
            if (this.model.exactYear == null) {
                return
            }

            this.schema.fields.ceilingYear.disabled = this.model.exactYear
            if (this.model.floorYear == null && this.model.ceilingYear != null) {
                this.model.floorYear = this.model.ceilingYear
            }
            else {
                this.model.ceilingYear = this.model.floorYear
            }

            if (this.model.exactYear) {
                this.schema.fields.ceilingYear.min = YEAR_MIN
                this.schema.fields.floorYear.max = YEAR_MAX
                this.$refs.form.validate()
            }
        },
        'model.floor': function (newValue, oldValue) {
            this.updateFieldsFromModel('floor')
        },
        'model.floorYear': function (newValue, oldValue) {
            if (this.model.floorYear === this.model.ceilingYear && this.model.floorYear != null) {
                this.model.exactYear = true
            }

            if (this.model.exactYear) {
                this.model.ceilingYear = this.model.floorYear
            }
            else {
                if (this.model.floorYear != null) {
                    this.schema.fields.ceilingYear.min = Math.max(YEAR_MIN, this.model.floorYear)
                }
                else {
                    this.schema.fields.ceilingYear.min = YEAR_MIN
                }
            }

            this.$refs.form.validate()
        },
        'model.floorDayMonth': function (newValue, oldValue) {
            if (this.model.exactYear && this.model.floorDayMonth === this.model.ceilingDayMonth && this.model.floorDayMonth != null) {
                this.model.exactDate = true
            }

            if (this.model.exactDate) {
                this.model.ceilingDayMonth = this.model.floorDayMonth
            }

            this.$refs.form.validate()
        },
        'model.ceiling': function (newValue, oldValue) {
            this.updateFieldsFromModel('ceiling')
        },
        'model.ceilingYear': function (newValue, oldValue) {
            if (this.model.floorYear === this.model.ceilingYear && this.model.floorYear != null) {
                this.model.exactYear = true
            }

            if (!this.model.exactYear) {
                if (this.model.ceilingYear != null) {
                    this.schema.fields.floorYear.max = Math.min(YEAR_MAX, this.model.ceilingYear)
                }
                else {
                    this.schema.fields.floorYear.max = YEAR_MAX
                }
            }

            this.$refs.form.validate()
        },
        'model.ceilingDayMonth': function (newValue, oldValue) {
            if (this.model.exactYear && this.model.floorDayMonth === this.model.ceilingDayMonth && this.model.floorDayMonth != null) {
                this.model.exactDate = true
            }
            this.$refs.form.validate()
        },
    },
    // set year min and max values

    methods: {
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            // If either floor are ceiling are changed, commit both
            if (
                (JSON.stringify(this.model.floorYear) !== JSON.stringify(this.originalModel.floorYear) && !(this.model.floorYear == null && this.originalModel.floorYear == null))
                || (JSON.stringify(this.model.ceilingYear) !== JSON.stringify(this.originalModel.ceilingYear) && !(this.model.ceilingYear == null && this.originalModel.ceilingYear == null))
                || (JSON.stringify(this.model.floorDayMonth) !== JSON.stringify(this.originalModel.floorDayMonth) && !(this.model.floorDayMonth == null && this.originalModel.floorDayMonth == null))
                || (JSON.stringify(this.model.ceilingDayMonth) !== JSON.stringify(this.originalModel.ceilingDayMonth) && !(this.model.ceilingDayMonth == null && this.originalModel.ceilingDayMonth == null))
            ) {
                for (let key of ['floor', 'ceiling']) {
                    this.changes.push({
                        'keyGroup': this.keyGroup,
                        'key': key,
                        'label': this.groupLabel == null ? {floor: 'Date from', ceiling: 'Date to'}[key] : this.groupLabel + ' ' + {floor: 'date from', ceiling: 'date to'}[key],
                        'old': this.formatDateHuman(this.originalModel[key + 'Year'], this.originalModel[key + 'DayMonth'], key),
                        'new': this.formatDateHuman(this.model[key + 'Year'], this.model[key + 'DayMonth'], key),
                        'value': this.formatDateComputer(this.model[key + 'Year'], this.model[key + 'DayMonth'], key),
                    })
                }
            }
        },
        formatDateComputer(year, dayMonth, key) {
            let defaultDayMonth = key === 'floor' ? '01-01' : '12-31'
            if (year == null) {
                return null
            }
            if (dayMonth == null) {
                return year + '-' + defaultDayMonth
            }
            return year + '-' + dayMonth.substr(3,2) + '-' + dayMonth.substr(0,2)
        },
        formatDateHuman(year, dayMonth, key) {
            let defaultDayMonth = key === 'floor' ? '01/01' : '31/12'
            if (year == null) {
                return null
            }
            if (dayMonth == null) {
                return defaultDayMonth + '/' + year
            }
            return dayMonth  + '/' + year
        },
        updateFieldsFromModel(key) {
            if (this.model[key] != null) {
                // date in format 'YYYY-MM-DDTHH:mm:ss'
                let dateString = (new Date(this.model[key])).toISOString()
                this.model[key + 'Year'] = Number(dateString.substr(0, 4))
                this.model[key + 'DayMonth'] = dateString.substr(8, 2) + '/' + dateString.substr(5, 2)
            }
            else {
                this.model[key + 'Year'] = null
                this.model[key + 'DayMonth'] = null
            }
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        validated(isValid, errors) {
            for (let key of ['floor', 'ceiling']) {
                // fix NaN
                if (isNaN(this.model[key + 'Year'])) {
                    this.model[key + 'Year'] = null
                    this.$refs.form.validate()
                    return
                }
                // fix empty DayMonth values
                if (this.model[key + 'DayMonth'] === '') {
                    this.model[key + 'DayMonth'] = null
                    this.$refs.form.validate()
                    return
                }
                // check if the complete date actually exists
                if (isValid && this.model[key + 'DayMonth'] != null) {
                    let date = new Date(this.model[key + 'Year'] + '-' + this.model[key + 'DayMonth'].substr(3,2) + '-' + this.model[key + 'DayMonth'].substr(0,2))
                    if (isNaN(date)) {
                        this.$refs.form.errors.push({
                            error: 'Invalid date',
                            field: this.schema.fields[key + 'DayMonth'],
                        })
                        this.isValid = false
                        return
                    }
                }
            }

            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
