<template>
    <panel :header="header">
        <table
            v-if="model.length > 0"
            class="table table-striped table-bordered table-hover"
        >
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Interval</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in model"
                    :key="index"
                    :class="errorArray.includes(index) ? 'danger' : ''"
                >
                    <td>{{ item.type }}</td>
                    <td>{{ formatFuzzyDate(item.date) }}</td>
                    <td>{{ formatFuzzyInterval(item.interval) }}</td>
                    <td>
                        <a
                            href="#"
                            title="Edit"
                            class="action"
                            @click.prevent="update(item, index)"
                        >
                            <i class="fa fa-pencil-square-o" />
                        </a>
                        <a
                            href="#"
                            title="Delete"
                            class="action"
                            @click.prevent="del(item, index)"
                        >
                            <i class="fa fa-trash-o" />
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
        <p
            v-if="errorArray.length"
            class="text-danger"
        >
            Born date must be before died date, attested dates and intervals must be in between born and died dates.
        </p>
        <btn
            v-if="typeValues.length"
            @click="add()"
        >
            <i class="fa fa-plus" /> Add a date or interval
        </btn>

        <modal
            v-model="editModal"
            size="lg"
            auto-focus
            :backdrop="false"
        >
            <alert type="warning">
                For centuries, please use the format XX01 – XX00, e.g. 1201 – 1300. For all other timespans, please consult the Vademecum.
            </alert>
            <vue-form-generator
                ref="typeForm"
                :schema="typeSchema"
                :model="editModel"
                :options="formOptions"
                @validated="editValidated"
                @model-updated="modelUpdated"
            />
            <div
                class="row"
            >
                <div :class="editModel.isInterval ? 'col-sm-5' : 'col-sm-11'">
                    <h2 v-if="editModel.isInterval">Start of interval</h2>
                    <vue-form-generator
                        ref="startForm"
                        :schema="startSchema"
                        :model="editModel.start"
                        :options="dateFormOptions"
                        @validated="editValidated"
                        @model-updated="modelUpdated"
                    />
                </div>
                <auto-date
                    :model="editModel.start"
                    :offset="editModel.isInterval ? 30 : 0"
                    @set-floor-day-month="setFloorDayMonth('start')"
                    @set-ceiling-year="setCeilingYear('start')"
                    @set-ceiling-day-month="setCeilingDayMonth('start')"
                />
                <div
                    v-if="editModel.isInterval"
                    class="col-sm-5"
                >
                    <h2>End of interval</h2>
                    <vue-form-generator
                        ref="endForm"
                        :schema="endSchema"
                        :model="editModel.end"
                        :options="dateFormOptions"
                        @validated="editValidated"
                        @model-updated="modelUpdated"
                    />
                </div>
                <auto-date
                    v-if="editModel.isInterval"
                    :model="editModel.end"
                    :offset="30"
                    @set-floor-day-month="setFloorDayMonth('end')"
                    @set-ceiling-year="setCeilingYear('end')"
                    @set-ceiling-day-month="setCeilingDayMonth('end')"
                />
            </div>
            <div slot="header">
                <h4
                    v-if="editModel.index != null"
                    class="modal-title"
                >
                    Edit date
                </h4>
                <h4
                    v-else
                    class="modal-title"
                >
                    Add a new date
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!editModel.valid"
                    @click="submitEdit()"
                >
                    {{ editModel.index == null ? 'Add' : 'Update' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            title="Delete date"
            auto-focus
            :append-to-body="true"
        >
            <p>Are you sure you want to delete this date?</p>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDelete()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'
import AutoDate from './Components/AutoDate'

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);
Vue.component('autoDate', AutoDate);

const $ = require('jquery');

const YEAR_MIN = -5000;
const YEAR_MAX = (new Date()).getFullYear();

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        model: {
            type: Array,
            default: () => {return [];},
        },
        config: {
            type: Object,
            default: () => {return {};},
        },
    },
    data() {
        let data = {
            errorArray: [],
            typeValues: [],
            editModal: false,
            editModel: {
                index: null,
                valid: null,
                type: null,
                // false: date, true: interval
                isInterval: null,
                start: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                end: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },

            },
            delModal: false,
            dateFormOptions: {
                validateAfterLoad: true,
                // All form validation is triggered by this.modelUpdated
                validateAfterChanged: false,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            typeSchema: {
                fields: {
                    type: {
                        type: 'multiselectClear',
                        label: 'Type',
                        labelClasses: 'control-label',
                        model: 'type',
                        values: [],
                        required: true,
                        validator: VueFormGenerator.validators.required,
                    },
                    isInterval: {
                        type: 'checkbox',
                        label: 'Toggle date / interval',
                        labelClasses: 'control-label',
                        model: 'isInterval',
                    },
                },
            },
            schema: {
                fields: {
                    floorYear: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        labelClasses: 'control-label floor-year',
                        model: 'floorYear',
                        required: this.floorDayMonth != null,
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                    },
                    floorDayMonth: {
                        type: 'input',
                        inputType: 'string',
                        label: 'Day from',
                        labelClasses: 'control-label floor-day-month',
                        model: 'floorDayMonth',
                        pattern: '^\\d{2}[/]\\d{2}$',
                        help: 'Please use the format "DD/MM", e.g. 24/03.',
                    },
                    ceilingYear: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        labelClasses: 'control-label ceiling-year',
                        model: 'ceilingYear',
                        min: YEAR_MIN,
                        max: YEAR_MAX,
                    },
                    ceilingDayMonth: {
                        type: 'input',
                        inputType: 'string',
                        label: 'Day to',
                        labelClasses: 'control-label ceiling-day-month',
                        model: 'ceilingDayMonth',
                        required: this.ceilingYear != null,
                        pattern: '^\\d{2}[/]\\d{2}$',
                        help: 'Please use the format "DD/MM", e.g. 24/03.',
                    },
                },
            },
        };
        data.startSchema = JSON.parse(JSON.stringify(data.schema));
        data.endSchema = JSON.parse(JSON.stringify(data.schema));
        data.startSchema.fields.floorYear.validator = [VueFormGenerator.validators.number, VueFormGenerator.validators.required, this.validateFloorYear, this.validateIntervalFloorYear];
        data.startSchema.fields.floorDayMonth.validator = [VueFormGenerator.validators.regexp, VueFormGenerator.validators.required, this.validateFloorDayMonth, this.validateIntervalFloorDayMonth];
        data.startSchema.fields.ceilingYear.validator = [VueFormGenerator.validators.number, VueFormGenerator.validators.required, this.validateCeilingYear, this.validateIntervalCeilingYear];
        data.startSchema.fields.ceilingDayMonth.validator = [VueFormGenerator.validators.regexp, VueFormGenerator.validators.required, this.validateCeilingDayMonth, this.validateIntervalCeilingDayMonth];
        data.endSchema.fields.floorYear.validator = [VueFormGenerator.validators.number, VueFormGenerator.validators.required, this.validateFloorYear, this.validateIntervalFloorYear];
        data.endSchema.fields.floorDayMonth.validator = [VueFormGenerator.validators.regexp, VueFormGenerator.validators.required, this.validateFloorDayMonth, this.validateIntervalFloorDayMonth];
        data.endSchema.fields.ceilingYear.validator = [VueFormGenerator.validators.number, VueFormGenerator.validators.required, this.validateCeilingYear, this.validateIntervalCeilingYear];
        data.endSchema.fields.ceilingDayMonth.validator = [VueFormGenerator.validators.regexp, VueFormGenerator.validators.required, this.validateCeilingDayMonth, this.validateIntervalCeilingDayMonth];
        return data;
    },
    watch: {
        'editModel' () {
            this.recalculateIsInterval();
        },
        'editModel.type' () {
            this.recalculateIsInterval();
        },
    },
    methods: {
        enableFields(enableKeys) {
            if (enableKeys == null) {
                this.recalculateTypeValues();
            }
        },
        modelUpdated() {
            this.$nextTick(function() {
                this.$refs.startForm.validate();
                if (this.$refs.endForm != null) {
                    this.$refs.endForm.validate();
                }
            });
        },
        recalculateTypeValues() {
            this.typeValues = [];
            for (let type of Object.keys(this.config)) {
                if (this.config[type].limit === 0) {
                    this.typeValues.push(type);
                } else if (this.model.filter(item => item.type === type).length < this.config[type].limit) {
                    this.typeValues.push(type);
                }
            }
            this.typeSchema.fields.type.values = this.typeValues;
        },
        recalculateIsInterval() {
            if (this.editModel != null && this.editModel.type != null && this.config[this.editModel.type].type === 'dateOrInterval')
            {
                this.typeSchema.fields.isInterval.visible = true;
            }
            else {
                this.typeSchema.fields.isInterval.visible = false;
                this.editModel.isInterval = false;
            }
        },
        validate() {
            this.validateTotal();
        },
        calcChanges() {
            this.changes = [];
            // dates is regarded as a single item
            if (JSON.stringify(this.model) !== JSON.stringify(this.originalModel)) {
                this.changes.push({
                    key: 'dates',
                    label: 'Dates',
                    'old': this.displayDates(this.originalModel),
                    'new': this.displayDates(this.model),
                    'value': this.model,
                })
            }
        },
        formatFuzzyDate(input) {
            if (input == null) {
                return '';
            }
            return this.formatFuzzyDatePart(input.floor) + '-' + this.formatFuzzyDatePart(input.ceiling, true);
        },
        formatFuzzyDatePart(input, isCeiling = false) {
            // If negative year: take second dash
            let yearLength = input.replace(/[^-]/g, "").length === 2 ? input.indexOf('-') : input.indexOf('-', 1);
            return input == null ? (isCeiling ? 'infinity' : '-infinity') : (input.substr(yearLength + 4, 2) + '/' + input.substr(yearLength + 1, 2) + '/' + input.substr(0, yearLength));
        },
        formatFuzzyInterval(input) {
            if (input == null) {
                return '';
            }
            return '[' + this.formatFuzzyDate(input.start) + ']' + ' - ' + '[' + this.formatFuzzyDate(input.end) + ']';
        },
        getFormDate(input) {
            let result = {
                floorYear: null,
                floorDayMonth: null,
                ceilingYear: null,
                ceilingDayMonth: null,
            };
            if (input.floor != null) {
                // If negative year: take second dash
                let yearLength = input.floor.replace(/[^-]/g, "").length === 2 ? input.floor.indexOf('-') : input.floor.indexOf('-', 1);
                result.floorYear = parseInt(input.floor.substr(0, yearLength));
                result.floorDayMonth = input.floor.substr(yearLength + 4, 2) + '/' + input.floor.substr(yearLength + 1, 2);
            }
            if (input.ceiling != null) {
                // If negative year: take second dash
                let yearLength = input.ceiling.replace(/[^-]/g, "").length === 2 ? input.ceiling.indexOf('-') : input.ceiling.indexOf('-', 1);
                result.ceilingYear = parseInt(input.ceiling.substr(0, yearLength));
                result.ceilingDayMonth = input.ceiling.substr(yearLength + 4, 2) + '/' + input.ceiling.substr(yearLength + 1, 2);
            }
            return result;
        },
        zeroPad(year) {
            const yearString = year.toString();
            if(yearString.indexOf('-') === 0) {
                return '-' + yearString.substring(1).padStart(4, '0');
            }
            return yearString.padStart(4, '0');
        },
        getTableDate(input) {
            return {
                floor: input.floorYear == null ? null : (this.zeroPad(input.floorYear) + '-' + input.floorDayMonth.substr(3,2) + '-' + input.floorDayMonth.substr(0,2)),
                ceiling: input.ceilingYear == null ? null :  (this.zeroPad(input.ceilingYear) + '-' + input.ceilingDayMonth.substr(3,2) + '-' + input.ceilingDayMonth.substr(0,2)),
            }
        },
        displayDates(model) {
            // Return null if model is empty (e.g. old values when cloning)
            if (Object.keys(model).length === 0) {
                return [];
            }
            let results = [];
            for (let item of model) {
                if (!item.isInterval) {
                    results.push(item.type + ': ' + this.formatFuzzyDate(item.date));
                } else {
                    results.push(item.type + ': ' + this.formatFuzzyInterval(item.interval));
                }
            }
            return results;
        },
        validateFloorYear(value, field, model) {
            let errors = [];
            if (isNaN(model.floorYear)) {
                model.floorYear = null;
                this.modelUpdated();
                return errors;
            }
            if (model.floorYear != null && model.ceilingYear != null && model.floorYear > model.ceilingYear) {
                errors.push('"Year from" must be smaller than or equal to "Year to".');
            }
            if (model.floorYear == null && model.floorDayMonth != null) {
                errors.push('"Year from" must be set if "Day from" is set.');
            }
            return errors;
        },
        validateFloorDayMonth(value, field, model) {
            let errors = [];
            if (model.floorDayMonth === '') {
                model.floorDayMonth = null;
                this.modelUpdated();
                return errors;
            }
            if (model.floorYear != null && model.floorDayMonth == null) {
                errors.push('"Day from" must be set if "Year from" is set.');
            }
            if (model.floorYear != null && model.floorYear === model.ceilingYear && model.floorDayMonth != null && model.ceilingDayMonth != null) {
                let floorMonth = parseInt(model.floorDayMonth.substr(3,2));
                let ceilingMonth = parseInt(model.ceilingDayMonth.substr(3,2));
                if (floorMonth > ceilingMonth) {
                    errors.push('Month in "Day from" must be smaller than or equal to month in "Day to".');
                } else if (floorMonth === ceilingMonth) {
                    let floorDay = parseInt(model.floorDayMonth.substr(0,2));
                    let ceilingDay = parseInt(model.ceilingDayMonth.substr(0,2));
                    if (floorDay > ceilingDay) {
                        errors.push('Day in "Day from" must be smaller than or equal to day in "Day to".');
                    }
                }
            }
            return errors;
        },
        validateCeilingYear(value, field, model) {
            let errors = [];
            if (isNaN(model.ceilingYear)) {
                model.ceilingYear = null;
                this.modelUpdated();
                return errors;
            }
            if (model.floorYear != null && model.ceilingYear != null && model.floorYear > model.ceilingYear) {
                errors.push('"Year to" must be larger than or equal to "Year to".');
            }
            if (model.ceilingYear == null && model.ceilingDayMonth != null) {
                errors.push('"Year to" must be set if "Day to" is set.');
            }
            return errors;
        },
        validateCeilingDayMonth(value, field, model) {
            let errors = [];
            if (model.ceilingDayMonth === '') {
                model.ceilingDayMonth = null;
                this.modelUpdated();
                return errors;
            }
            if (model.ceilingYear != null && model.ceilingDayMonth == null) {
                errors.push('"Day to" must be set if "Year to" is set.');
            }
            if (model.floorYear != null && model.floorYear === model.ceilingYear && model.floorDayMonth != null && model.ceilingDayMonth != null) {
                let floorMonth = parseInt(model.floorDayMonth.substr(3,2));
                let ceilingMonth = parseInt(model.ceilingDayMonth.substr(3,2));
                if (floorMonth > ceilingMonth) {
                    errors.push('Month in "Day to" must be larger than or equal to month in "Day from".');
                } else if (floorMonth === ceilingMonth) {
                    let floorDay = parseInt(model.floorDayMonth.substr(0,2));
                    let ceilingDay = parseInt(model.ceilingDayMonth.substr(0,2));
                    if (floorDay > ceilingDay) {
                        errors.push('Day in "Day to" must be larger than or equal to day in "Day from".');
                    }
                }
            }
            return errors;
        },
        validateIntervalFloorYear(value, field, model) {
            let errors = [];
            if (this.editModel.isInterval) {
                if (isNaN(model.floorYear)) {
                    // Wait for other validators to fix this value and revalidate
                    return errors;
                }
                if (field === this.endSchema.fields.floorYear && this.editModel.start.floorYear != null && this.editModel.end.floorYear == null) {
                    errors.push('End of interval "Year from" must be set if Start of interval "Year from" is set.');
                }
                if (this.editModel.start.floorYear != null && this.editModel.end.floorYear != null && this.editModel.start.floorYear > this.editModel.end.floorYear) {
                    errors.push('Start of interval "Year from" must be smaller than or equal to End of interval "Year from".');
                }
            }
            return errors;
        },
        validateIntervalFloorDayMonth(value, field, model) {
            let errors = [];
            if (this.editModel.isInterval) {
                if (model.floorDayMonth === '') {
                    // Wait for other validators to fix this value and revalidate
                    return errors;
                }
                if (this.editModel.start.floorYear != null && this.editModel.start.floorYear === this.editModel.end.floorYear && this.editModel.start.floorDayMonth != null && this.editModel.end.floorDayMonth != null) {
                    let startMonth = parseInt(this.editModel.start.floorDayMonth.substr(3,2));
                    let endMonth = parseInt(this.editModel.end.floorDayMonth.substr(3,2));
                    if (startMonth > endMonth) {
                        errors.push('Month in Start of interval "Day from" must be smaller than or equal to month in End of interval "Day from".');
                    } else if (startMonth === endMonth) {
                        let startDay = parseInt(this.editModel.start.floorDayMonth.substr(0,2));
                        let endDay = parseInt(this.editModel.end.floorDayMonth.substr(0,2));
                        if (startDay > endDay) {
                            errors.push('Day in Start of interval "Day from" must be smaller than or equal to day in End of interval "Day from".');
                        }
                    }
                }
            }
            return errors;
        },
        validateIntervalCeilingYear(value, field, model) {
            let errors = [];
            if (this.editModel.isInterval) {
                if (isNaN(model.ceilingYear)) {
                    // Wait for other validators to fix this value and revalidate
                    return errors;
                }
                if (field === this.startSchema.fields.ceilingYear && this.editModel.start.ceilingYear == null && this.editModel.end.ceilingYear != null) {
                    errors.push('Start of interval "Year to" must be set if End of interval "Year to" is set.');
                }
                if (this.editModel.start.ceilingYear != null && this.editModel.end.ceilingYear != null && this.editModel.start.ceilingYear > this.editModel.end.ceilingYear) {
                    errors.push('Start of interval "Year to" must be smaller than or equal to End of interval "Year to".');
                }
            }
            return errors;
        },
        validateIntervalCeilingDayMonth(value, field, model) {
            let errors = [];
            if (this.editModel.isInterval) {
                if (model.ceilingDayMonth === '') {
                    // Wait for other validators to fix this value and revalidate
                    return errors;
                }
                if (this.editModel.start.ceilingYear != null && this.editModel.start.ceilingYear === this.editModel.end.ceilingYear && this.editModel.start.ceilingDayMonth != null && this.editModel.end.ceilingDayMonth != null) {
                    let startMonth = parseInt(this.editModel.start.ceilingDayMonth.substr(3,2));
                    let endMonth = parseInt(this.editModel.end.ceilingDayMonth.substr(3,2));
                    if (startMonth > endMonth) {
                        errors.push('Month in Start of interval "Day from" must be smaller than or equal to month in End of interval "Day from".');
                    } else if (startMonth === endMonth) {
                        let startDay = parseInt(this.editModel.start.ceilingDayMonth.substr(0,2));
                        let endDay = parseInt(this.editModel.end.ceilingDayMonth.substr(0,2));
                        if (startDay > endDay) {
                            errors.push('Day in Start of interval "Day from" must be smaller than or equal to day in End of interval "Day from".');
                        }
                    }
                }
            }
            return errors;
        },
        setFloorDayMonth(form) {
            this.editModel[form].floorDayMonth = '01/01';
            this.$refs[form + 'Form'].validate();
        },
        setCeilingYear(form) {
            this.editModel[form].ceilingYear = this.editModel[form].floorYear;
            this.$refs[form + 'Form'].validate();
        },
        setCeilingDayMonth(form) {
            this.editModel[form].ceilingDayMonth = '31/12';
            this.$refs[form + 'Form'].validate();
        },
        add() {
            this.editModel = {
                index: null,
                valid: false,
                type: null,
                // false: date, true: interval
                isInterval: null,
                start: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                end: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
            };
            this.editModal = true;
        },
        update(item, index) {
            this.editModel = {
                valid: true,
                type: item.type,
                isInterval: item.isInterval,
                index: index,
                start: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                end: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
            };
            if (!item.isInterval) {
                this.editModel.start = this.getFormDate(item.date);
            } else {
                this.editModel.start = this.getFormDate(item.interval.start);
                this.editModel.end = this.getFormDate(item.interval.end);
            }
            this.editModal = true;
        },
        del(item, index) {
            this.editModel = {
                index: index,
                type: null,
                // false: date, true: interval
                isInterval: null,
                start: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                end: {
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
            };
            this.delModal = true;
        },
        editValidated() {
            // Fix auto icon position (possibly broken by error messages on fields above)
            this.$nextTick(function() {
                $.each(['floor-day-month', 'ceiling-year', 'ceiling-day-month'], (i, v) => {
                    $('.auto-' + v).each(function() {
                        $(this).css('top', $(this).closest('.col-sm-1').prev().find('.' + v).position().top + $(this).closest('.col-sm-1').prev().find('.' + v).height() + 15 + 'px');
                    });
                });
            });
            this.editModel.valid = (
                this.editModel.type != null
                && this.$refs.typeForm.errors.length === 0
                && this.$refs.startForm.errors.length === 0
                && (
                    this.$refs.endForm == null
                    || this.$refs.endForm.errors.length === 0
                )
            );
        },
        submitEdit() {
            if (this.editModel.index == null) {
                if (!this.editModel.isInterval) {
                    this.model.push({
                        date: this.getTableDate(this.editModel.start),
                        isInterval: this.editModel.isInterval,
                        type: this.editModel.type,
                    })
                } else {
                    this.model.push({
                        interval: {
                            start: this.getTableDate(this.editModel.start),
                            end: this.getTableDate(this.editModel.end),
                        },
                        isInterval: this.editModel.isInterval,
                        type: this.editModel.type,
                    })
                }
            } else {
                if (!this.editModel.isInterval) {
                    delete this.model[this.editModel.index].interval;
                    this.model[this.editModel.index].date = this.getTableDate(this.editModel.start);
                    this.model[this.editModel.index].isInterval = this.editModel.isInterval;
                    this.model[this.editModel.index].type = this.editModel.type;
                } else {
                    delete this.model[this.editModel.index].date;
                    this.model[this.editModel.index].interval = {
                        start: this.getTableDate(this.editModel.start),
                        end: this.getTableDate(this.editModel.end),
                    };
                    this.model[this.editModel.index].isInterval = this.editModel.isInterval;
                    this.model[this.editModel.index].type = this.editModel.type;
                }
            }
            this.recalculateTypeValues();
            this.validateTotal();
            this.calcChanges();
            this.$emit('validated', this.isValid, null, this)
            this.editModal = false;
        },
        submitDelete() {
            this.model.splice(this.editModel.index, 1);
            this.recalculateTypeValues();
            this.validateTotal();
            this.calcChanges();
            this.$emit('validated', this.isValid, null, this)
            this.delModal = false;
        },
        validateTotal() {
            for (let i = 0; i < this.model.length; i++) {
                this.model[i].index = i;
            }
            let born = this.model.filter((item) => item.type === 'born');
            let died = this.model.filter((item) => item.type === 'died');
            let attested = this.model.filter((item) => item.type === 'attested');
            let verifyArray = [];
            let errorSet = new Set();
            if (born.length === 1 && died.length === 1) {
                verifyArray.push([born[0], died[0]]);
            }
            if (born.length === 1) {
                for (let att of attested) {
                    verifyArray.push([born[0], att]);
                }
            }
            if (died.length === 1) {
                for (let att of attested) {
                    verifyArray.push([att, died[0]]);
                }
            }
            for (let verify of verifyArray) {
                let first = this.splitDateOrInterval(verify[0]);
                let second = this.splitDateOrInterval(verify[1]);
                // date and date
                if ('floor' in first && 'floor' in second) {
                    let floorCeilArray = ['floor', 'ceiling'];
                    if (first.type === 'attested') {
                        floorCeilArray = ['ceiling'];
                    }
                    else if (second.type === 'attested') {
                        floorCeilArray = ['floor'];
                    }
                    for (let floorCeil of floorCeilArray) {
                        if (first[floorCeil] == null || second[floorCeil] == null) {
                            continue;
                        }
                        if (first[floorCeil].year > second[floorCeil].year) {
                            errorSet.add(verify[0].index);
                            errorSet.add(verify[1].index);
                        }
                        else if (first[floorCeil].year === second[floorCeil].year) {
                            if (first[floorCeil].month > second[floorCeil].month) {
                                errorSet.add(verify[0].index);
                                errorSet.add(verify[1].index);
                            }
                            else if (first[floorCeil].month === second[floorCeil].month) {
                                if (first[floorCeil].day > second[floorCeil].day) {
                                    errorSet.add(verify[0].index);
                                    errorSet.add(verify[1].index);
                                }
                            }
                        }
                    }
                }
                // date and interval
                else if ('floor' in first && !('floor' in second)) {
                    let floorCeilArray = ['floor', 'ceiling'];
                    if (first.type === 'attested') {
                        floorCeilArray = ['ceiling'];
                    }
                    else if (second.type === 'attested') {
                        floorCeilArray = ['floor'];
                    }
                    for (let floorCeil of floorCeilArray) {
                        if (first[floorCeil] == null || second.start[floorCeil] == null) {
                            continue;
                        }
                        if (first[floorCeil].year > second.start[floorCeil].year) {
                            errorSet.add(verify[0].index);
                            errorSet.add(verify[1].index);
                        }
                        else if (first[floorCeil].year === second.start[floorCeil].year) {
                            if (first[floorCeil].month > second.start[floorCeil].month) {
                                errorSet.add(verify[0].index);
                                errorSet.add(verify[1].index);
                            }
                            else if (first[floorCeil].month === second.start[floorCeil].month) {
                                if (first[floorCeil].day > second.start[floorCeil].day) {
                                    errorSet.add(verify[0].index);
                                    errorSet.add(verify[1].index);
                                }
                            }
                        }
                    }
                }
                // interval and date
                else if (!('floor' in first) && 'floor' in second) {
                    let floorCeilArray = ['floor', 'ceiling'];
                    if (first.type === 'attested') {
                        floorCeilArray = ['ceiling'];
                    }
                    else if (second.type === 'attested') {
                        floorCeilArray = ['floor'];
                    }
                    for (let floorCeil of floorCeilArray) {
                        if (first.end[floorCeil] == null || second[floorCeil] == null) {
                            continue;
                        }
                        if (first.end[floorCeil].year > second[floorCeil].year) {
                            errorSet.add(verify[0].index);
                            errorSet.add(verify[1].index);
                        }
                        else if (first.end[floorCeil].year === second[floorCeil].year) {
                            if (first.end[floorCeil].month > second[floorCeil].month) {
                                errorSet.add(verify[0].index);
                                errorSet.add(verify[1].index);
                            }
                            else if (first.end[floorCeil].month === second[floorCeil].month) {
                                if (first.end[floorCeil].day > second[floorCeil].day) {
                                    errorSet.add(verify[0].index);
                                    errorSet.add(verify[1].index);
                                }
                            }
                        }
                    }
                }
            }
            this.isValid = errorSet.size === 0;
            this.errorArray = Array.from(errorSet);
        },
        splitDateOrInterval(input) {
            let yearLength = {};
            if (!input.isInterval) {
                for (let floorCeil of ['floor', 'ceiling']) {
                    let date = input.date[floorCeil];
                    yearLength[floorCeil] = date.replace(/[^-]/g, "").length === 2 ? date.indexOf('-') : date.indexOf('-', 1);
                }
                return {
                    floor: input.date.floor == null ? null : {
                        year: parseInt(input.date.floor.substr(0, yearLength.floor)),
                        month: parseInt(input.date.floor.substr(yearLength.floor + 1, 2)),
                        day: parseInt(input.date.floor.substr(yearLength.floor + 4, 2)),
                    },
                    ceiling: input.date.ceiling == null ? null : {
                        year: parseInt(input.date.ceiling.substr(0, yearLength.ceiling)),
                        month: parseInt(input.date.ceiling.substr(yearLength.ceiling + 1, 2)),
                        day: parseInt(input.date.ceiling.substr(yearLength.ceiling + 4, 2)),
                    },
                    type: input.type,
                }
            } else {
                for (let startEnd of ['start', 'end']) {
                    yearLength[startEnd] = {};
                    for (let floorCeil of ['floor', 'ceiling']) {
                        let date = input.interval[startEnd][floorCeil];
                        yearLength[startEnd][floorCeil] = date.replace(/[^-]/g, "").length === 2 ? date.indexOf('-') : date.indexOf('-', 1);
                    }
                }
                return {
                    start: {
                        floor: input.interval.start.floor == null ? null : {
                            year: parseInt(input.interval.start.floor.substr(0, yearLength.start.floor)),
                            month: parseInt(input.interval.start.floor.substr(yearLength.start.floor +1, 2)),
                            day: parseInt(input.interval.start.floor.substr(yearLength.start.floor + 4, 2)),
                        },
                        ceiling: input.interval.start.ceiling == null ? null : {
                            year: parseInt(input.interval.start.ceiling.substr(0, yearLength.start.ceiling)),
                            month: parseInt(input.interval.start.ceiling.substr(yearLength.start.ceiling + 1, 2)),
                            day: parseInt(input.interval.start.ceiling.substr(yearLength.start.ceiling + 4, 2)),
                        }
                    },
                    end: {
                        floor: input.interval.end.floor == null ? null : {
                            year: parseInt(input.interval.end.floor.substr(0, yearLength.end.floor)),
                            month: parseInt(input.interval.end.floor.substr(yearLength.end.floor + 1, 2)),
                            day: parseInt(input.interval.end.floor.substr(yearLength.end.floor + 4, 2)),
                        },
                        ceiling: input.interval.end.ceiling == null ? null : {
                            year: parseInt(input.interval.end.ceiling.substr(0, yearLength.end.ceiling)),
                            month: parseInt(input.interval.end.ceiling.substr(yearLength.end.ceiling + 1, 2)),
                            day: parseInt(input.interval.end.ceiling.substr(yearLength.end.ceiling + 4, 2)),
                        }
                    },
                    type: input.type,
                }
            }
        },
    }
}
</script>
