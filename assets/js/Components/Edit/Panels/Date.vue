<template>
    <panel :header="header">
        <vue-form-generator
            :schema="schema"
            :model="model"
            :options="formOptions"
            ref="dateForm"
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

import Abstract from '../Abstract'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

var YEAR_MIN = 1
var YEAR_MAX = (new Date()).getFullYear()

export default {
    mixins: [ Abstract ],
    data() {
        return {
            schema: {
                fields: {
                    same_year: {
                        type: 'checkbox',
                        label: 'Exact year',
                        model: 'same_year',
                        default: false
                    },
                    floor: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        model: 'floor',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    },
                    ceiling: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'ceiling',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    }
                }
            }
        }
    },
    computed: {
        warnEstimate: function() {
            if (this.model == null || this.model.floor == null || this.model.ceiling == null) {
                return false
            }
            if (this.model.floor !== this.model.ceiling && this.model.floor % 25 === 0 && this.model.ceiling % 25 === 0) {
                return true
            }
            return false
        }
    },
    watch: {
        'model.same_year': function (newValue, oldValue) {
            if (this.model.same_year == null) {
                return
            }

            this.schema.fields.ceiling.disabled = this.model.same_year
            if (this.model.floor == null && this.model.ceiling != null) {
                this.model.floor = this.model.ceiling
            }
            else {
                this.model.ceiling = this.model.floor
            }
            if (this.model.same_year) {
                this.schema.fields.ceiling.min = YEAR_MIN
                this.schema.fields.floor.max = YEAR_MAX
                this.$refs.dateForm.validate()
            }
        },
        'model.floor': function (newValue, oldValue) {
            if (this.model.floor === this.model.ceiling && this.model.floor != null) {
                this.model.same_year = true
            }

            if (this.model.same_year) {
                this.model.ceiling = this.model.floor
            }
            else {
                if (this.model.floor != null) {
                    this.schema.fields.ceiling.min = Math.max(YEAR_MIN, this.model.floor)
                }
                else {
                    this.schema.fields.ceiling.min = YEAR_MIN
                }
            }
            this.$refs.dateForm.validate()
        },
        'model.ceiling': function (newValue, oldValue) {
            if (this.model.floor === this.model.ceiling && this.model.ceiling != null) {
                this.model.same_year = true
            }

            if (!this.model.same_year) {
                if (this.model.ceiling != null) {
                    this.schema.fields.floor.max = Math.min(YEAR_MAX, this.model.ceiling)
                }
                else {
                    this.schema.fields.floor.max = YEAR_MAX
                }
            }
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
                (JSON.stringify(this.model.floor) !== JSON.stringify(this.originalModel.floor) && !(this.model.floor == null && this.originalModel.floor == null))
                || (JSON.stringify(this.model.ceiling) !== JSON.stringify(this.originalModel.ceiling) && !(this.model.ceiling == null && this.originalModel.ceiling == null))
            ) {
                for (let key of ['floor', 'ceiling']) {
                    this.changes.push({
                        'keyGroup': 'date',
                        'key': key,
                        'label': this.fields[key].label,
                        'old': this.originalModel[key],
                        'new': this.model[key],
                        'value': this.model[key],
                    })
                }
            }
        },
        validated(isValid, errors) {
            // fix NaN
            for (let field of ['floor', 'ceiling']) {
                if (isNaN(this.model[field])) {
                    this.model[field] = null
                    this.$refs.dateForm.validate()
                    return
                }
            }
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        }
    }
}
</script>
