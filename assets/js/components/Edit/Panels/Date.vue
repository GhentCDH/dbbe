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

          <a href="#"
          title="Edit"
          class="action"
          @click.prevent="update(item, index)"
          >
          <i class="fa fa-pencil-square-o" />
          </a>

          <a href="#"
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
        :model-value="editModal"
        size="lg"
        auto-focus
    >
      <Alerts
          :alerts="warningAlerts"
          @dismiss="() => {}"
      />
      <vue-form-generator
          ref="typeFormRef"
          :schema="typeSchema"
          :model="editModel"
          :options="formOptions"
          @validated="editValidated"
          @model-updated="modelUpdated"
      />
      <div class="row">
        <div :class="editModel.isInterval ? 'col-sm-5' : 'col-sm-11'">
          <h2 v-if="editModel.isInterval">Start of interval</h2>
          <vue-form-generator
              ref="startFormRef"
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
              ref="endFormRef"
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
      <template #header>
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
      </template>
      <template #footer>
        <btn @click="editModal = null">Cancel</btn>
        <btn
            type="success"
            :disabled="!editModel.valid"
            @click="submitEdit()"
        >
          {{ editModel.index == null ? 'Add' : 'Update' }}
        </btn>
      </template>
    </modal>
    <modal
        :model-value="delModal"
        title="Delete date"
        auto-focus
        :append-to-body="true"
    >
      <p>Are you sure you want to delete this date?</p>
      <template #footer>
        <btn @click="delModal = null">Cancel</btn>
        <btn
            type="danger"
            @click="submitDelete()"
        >
          Delete
        </btn>
      </template>
    </modal>
  </panel>
</template>

<script setup>
import { ref, reactive, watch, nextTick } from 'vue'
import AutoDate from './Components/AutoDate.vue'
import validatorUtil from "@/helpers/validatorUtil"
import { disableFields as disableFieldsUtil, enableFields as enableFieldsUtil } from "@/helpers/formFieldUtils"
import { calcChanges as calcChangesUtil } from "@/helpers/modelChangeUtil"
import Alerts from "@/components/Alerts.vue"
import { Btn, Modal } from "uiv"

const YEAR_MIN = -5000
const YEAR_MAX = (new Date()).getFullYear()

const props = defineProps({
  model: {
    type: Array,
    default: () => []
  },
  config: {
    type: Object,
    default: () => ({})
  },
  header: {
    type: String,
    default: ''
  },
  links: {
    type: Array,
    default: () => []
  },
  reloads: {
    type: Array,
    default: () => []
  },
  values: {
    type: Array,
    default: () => []
  },
  keys: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['validated', 'reload'])
const warningAlerts = ref([
  {
    type: 'warning',
    message: 'For centuries, please use the format XX01 – XX00, e.g. 1201 – 1300. For all other timespans, please consult the Vademecum.'
  }
])

// Refs
const typeFormRef = ref(null)
const startFormRef = ref(null)
const endFormRef = ref(null)

// Reactive state
const errorArray = ref([])
const typeValues = ref([])
const editModal = ref(null)
const delModal = ref(false)
const changes = ref([])
const isValid = ref(true)
const originalModel = ref({})

const editModel = reactive({
  index: null,
  valid: null,
  type: null,
  isInterval: null,
  start: {
    floorYear: null,
    floorDayMonth: null,
    ceilingYear: null,
    ceilingDayMonth: null
  },
  end: {
    floorYear: null,
    floorDayMonth: null,
    ceilingYear: null,
    ceilingDayMonth: null
  }
})

const dateFormOptions = {
  validateAfterLoad: true,
  validateAfterChanged: false,
  validationErrorClass: "has-error",
  validationSuccessClass: "success"
}

const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success'
}

// Schemas
const typeSchema = reactive({
  fields: {
    type: {
      type: 'multiselectClear',
      label: 'Type',
      labelClasses: 'control-label',
      model: 'type',
      values: [],
      required: true,
      validator: validatorUtil.required
    },
    isInterval: {
      type: 'checkbox',
      label: 'Toggle date / interval',
      labelClasses: 'control-label',
      model: 'isInterval'
    }
  }
})

const baseSchema = {
  fields: {
    floorYear: {
      type: 'input',
      inputType: 'number',
      label: 'Year from',
      labelClasses: 'control-label floor-year',
      model: 'floorYear',
      min: YEAR_MIN,
      max: YEAR_MAX
    },
    floorDayMonth: {
      type: 'input',
      inputType: 'string',
      label: 'Day from',
      labelClasses: 'control-label floor-day-month',
      model: 'floorDayMonth',
      pattern: '^\\d{2}/\\d{2}$',
      help: 'Please use the format "DD/MM", e.g. 24/03.'
    },
    ceilingYear: {
      type: 'input',
      inputType: 'number',
      label: 'Year to',
      labelClasses: 'control-label ceiling-year',
      model: 'ceilingYear',
      min: YEAR_MIN,
      max: YEAR_MAX
    },
    ceilingDayMonth: {
      type: 'input',
      inputType: 'string',
      label: 'Day to',
      labelClasses: 'control-label ceiling-day-month',
      model: 'ceilingDayMonth',
      pattern: '^\\d{2}/\\d{2}$',
      help: 'Please use the format "DD/MM", e.g. 24/03.'
    }
  }
}

const startSchema = reactive(JSON.parse(JSON.stringify(baseSchema)))
const endSchema = reactive(JSON.parse(JSON.stringify(baseSchema)))

startSchema.fields.floorYear.validator = [validatorUtil.number, validatorUtil.required, validateFloorYear, validateIntervalFloorYear]
startSchema.fields.floorDayMonth.validator = [validatorUtil.regexp, validatorUtil.required, validateFloorDayMonth, validateIntervalFloorDayMonth]
startSchema.fields.ceilingYear.validator = [validatorUtil.number, validatorUtil.required, validateCeilingYear, validateIntervalCeilingYear]
startSchema.fields.ceilingDayMonth.validator = [validatorUtil.regexp, validatorUtil.required, validateCeilingDayMonth, validateIntervalCeilingDayMonth]

endSchema.fields.floorYear.validator = [validatorUtil.number, validatorUtil.required, validateFloorYear, validateIntervalFloorYear]
endSchema.fields.floorDayMonth.validator = [validatorUtil.regexp, validatorUtil.required, validateFloorDayMonth, validateIntervalFloorDayMonth]
endSchema.fields.ceilingYear.validator = [validatorUtil.number, validatorUtil.required, validateCeilingYear, validateIntervalCeilingYear]
endSchema.fields.ceilingDayMonth.validator = [validatorUtil.regexp, validatorUtil.required, validateCeilingDayMonth, validateIntervalCeilingDayMonth]

watch(() => editModel.type, () => {
  recalculateIsInterval()
})

watch(editModel, () => {
  recalculateIsInterval()
})

function init() {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  recalculateTypeValues()
}

function reload(type) {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

function validated(isValidValue, errors) {
  isValid.value = isValidValue
  changes.value = calcChangesUtil(props.model, originalModel.value, {})
  emit('validated', isValidValue, errors, this)
}

function disableFields(disableKeys) {
  if (!disableKeys) return
  const fields = {}  // or define proper fields if needed
  disableFieldsUtil(props.keys, fields, disableKeys)
}

function enableFields(enableKeys) {
  if (enableKeys == null) {
    recalculateTypeValues()
    return
  }
  const fields = {}  // or define proper fields if needed
  enableFieldsUtil(props.keys, fields, props.values, enableKeys)
}

function modelUpdated() {
  nextTick(() => {
    startFormRef.value?.validate()
    if (endFormRef.value != null) {
      endFormRef.value.validate()
    }
  })
}

function recalculateTypeValues() {
  typeValues.value = []
  for (let type of Object.keys(props.config)) {
    if (props.config[type].limit === 0) {
      typeValues.value.push(type)
    } else if (props.model.filter(item => item.type === type).length < props.config[type].limit) {
      typeValues.value.push(type)
    }
  }
  typeSchema.fields.type.values = typeValues.value
}

function recalculateIsInterval() {
  if (editModel != null && editModel.type != null && props.config[editModel.type]?.type === 'dateOrInterval') {
    typeSchema.fields.isInterval.visible = true
  } else {
    typeSchema.fields.isInterval.visible = false
    editModel.isInterval = false
  }
}

function validate() {
  validateTotal()
}

function calcChanges() {
  changes.value = []
  if (JSON.stringify(props.model) !== JSON.stringify(originalModel.value)) {
    changes.value.push({
      key: 'dates',
      label: 'Dates',
      'old': displayDates(originalModel.value),
      'new': displayDates(props.model),
      'value': props.model
    })
  }
}
function formatFuzzyDate(input) {
  if (input == null) {
    return ''
  }
  return formatFuzzyDatePart(input.floor) + '-' + formatFuzzyDatePart(input.ceiling, true)
}
function formatFuzzyDatePart(input, isCeiling = false) {
  if (input == null) {
    return isCeiling ? 'infinity' : '-infinity'
  }
  let yearLength = input.replace(/[^-]/g, "").length === 2 ? input.indexOf('-') : input.indexOf('-', 1)
  return input.substr(yearLength + 4, 2) + '/' + input.substr(yearLength + 1, 2) + '/' + input.substr(0, yearLength)
}

function formatFuzzyInterval(input) {
  if (input == null) {
    return ''
  }
  return '[' + formatFuzzyDate(input.start) + ']' + ' - ' + '[' + formatFuzzyDate(input.end) + ']'
}
function getFormDate(input) {
  let result = {
    floorYear: null,
    floorDayMonth: null,
    ceilingYear: null,
    ceilingDayMonth: null
  }
  if (input.floor != null) {
    let yearLength = input.floor.replace(/[^-]/g, "").length === 2 ? input.floor.indexOf('-') : input.floor.indexOf('-', 1)
    result.floorYear = parseInt(input.floor.substr(0, yearLength))
    result.floorDayMonth = input.floor.substr(yearLength + 4, 2) + '/' + input.floor.substr(yearLength + 1, 2)
  }
  if (input.ceiling != null) {
    let yearLength = input.ceiling.replace(/[^-]/g, "").length === 2 ? input.ceiling.indexOf('-') : input.ceiling.indexOf('-', 1)
    result.ceilingYear = parseInt(input.ceiling.substr(0, yearLength))
    result.ceilingDayMonth = input.ceiling.substr(yearLength + 4, 2) + '/' + input.ceiling.substr(yearLength + 1, 2)
  }
  return result
}

function zeroPad(year) {
  const yearString = year.toString()
  if (yearString.indexOf('-') === 0) {
    return '-' + yearString.substring(1).padStart(4, '0')
  }
  return yearString.padStart(4, '0')
}

function getTableDate(input) {
  return {
    floor: input.floorYear == null ? null : (zeroPad(input.floorYear) + '-' + input.floorDayMonth.substr(3, 2) + '-' + input.floorDayMonth.substr(0, 2)),
    ceiling: input.ceilingYear == null ? null : (zeroPad(input.ceilingYear) + '-' + input.ceilingDayMonth.substr(3, 2) + '-' + input.ceilingDayMonth.substr(0, 2))
  }
}

function displayDates(model) {
  if (Object.keys(model).length === 0) {
    return []
  }
  let results = []
  for (let item of model) {
    if (!item.isInterval) {
      results.push(item.type + ': ' + formatFuzzyDate(item.date))
    } else {
      results.push(item.type + ': ' + formatFuzzyInterval(item.interval))
    }
  }
  return results
}

function validateFloorYear(value, field, model) {
  let errors = []
  if (isNaN(model.floorYear)) {
    model.floorYear = null
    modelUpdated()
    return errors
  }
  if (model.floorYear != null && model.ceilingYear != null && model.floorYear > model.ceilingYear) {
    errors.push('"Year from" must be smaller than or equal to "Year to".')
  }
  if (model.floorYear == null && model.floorDayMonth != null) {
    errors.push('"Year from" must be set if "Day from" is set.')
  }
  return errors
}

function validateFloorDayMonth(value, field, model) {
  let errors = []
  if (model.floorDayMonth === '') {
    model.floorDayMonth = null
    modelUpdated()
    return errors
  }
  if (model.floorYear != null && model.floorDayMonth == null) {
    errors.push('"Day from" must be set if "Year from" is set.')
  }
  if (model.floorYear != null && model.floorYear === model.ceilingYear && model.floorDayMonth != null && model.ceilingDayMonth != null) {
    let floorMonth = parseInt(model.floorDayMonth.substr(3, 2))
    let ceilingMonth = parseInt(model.ceilingDayMonth.substr(3, 2))
    if (floorMonth > ceilingMonth) {
      errors.push('Month in "Day from" must be smaller than or equal to month in "Day to".')
    } else if (floorMonth === ceilingMonth) {
      let floorDay = parseInt(model.floorDayMonth.substr(0, 2))
      let ceilingDay = parseInt(model.ceilingDayMonth.substr(0, 2))
      if (floorDay > ceilingDay) {
        errors.push('Day in "Day from" must be smaller than or equal to day in "Day to".')
      }
    }
  }
  return errors
}

function validateCeilingYear(value, field, model) {
  let errors = []
  if (isNaN(model.ceilingYear)) {
    model.ceilingYear = null
    modelUpdated()
    return errors
  }
  if (model.floorYear != null && model.ceilingYear != null && model.floorYear > model.ceilingYear) {
    errors.push('"Year to" must be larger than or equal to "Year from".')
  }
  if (model.ceilingYear == null && model.ceilingDayMonth != null) {
    errors.push('"Year to" must be set if "Day to" is set.')
  }
  return errors
}

function validateCeilingDayMonth(value, field, model) {
  let errors = []
  if (model.ceilingDayMonth === '') {
    model.ceilingDayMonth = null
    modelUpdated()
    return errors
  }
  if (model.ceilingYear != null && model.ceilingDayMonth == null) {
    errors.push('"Day to" must be set if "Year to" is set.')
  }
  if (model.floorYear != null && model.floorYear === model.ceilingYear && model.floorDayMonth != null && model.ceilingDayMonth != null) {
    let floorMonth = parseInt(model.floorDayMonth.substr(3, 2))
    let ceilingMonth = parseInt(model.ceilingDayMonth.substr(3, 2))
    if (floorMonth > ceilingMonth) {
      errors.push('Month in "Day to" must be larger than or equal to month in "Day from".')
    } else if (floorMonth === ceilingMonth) {
      let floorDay = parseInt(model.floorDayMonth.substr(0, 2))
      let ceilingDay = parseInt(model.ceilingDayMonth.substr(0, 2))
      if (floorDay > ceilingDay) {
        errors.push('Day in "Day to" must be larger than or equal to day in "Day from".')
      }
    }
  }
  return errors
}

function validateIntervalFloorYear(value, field, model) {
  let errors = []
  if (editModel.isInterval) {
    if (isNaN(model.floorYear)) {
      return errors
    }
    if (field === endSchema.fields.floorYear && editModel.start.floorYear != null && editModel.end.floorYear == null) {
      errors.push('End of interval "Year from" must be set if Start of interval "Year from" is set.')
    }
    if (editModel.start.floorYear != null && editModel.end.floorYear != null && editModel.start.floorYear > editModel.end.floorYear) {
      errors.push('Start of interval "Year from" must be smaller than or equal to End of interval "Year from".')
    }
  }
  return errors
}

function validateIntervalFloorDayMonth(value, field, model) {
  let errors = []
  if (editModel.isInterval) {
    if (model.floorDayMonth === '') {
      return errors
    }
    if (editModel.start.floorYear != null && editModel.start.floorYear === editModel.end.floorYear && editModel.start.floorDayMonth != null && editModel.end.floorDayMonth != null) {
      let startMonth = parseInt(editModel.start.floorDayMonth.substr(3, 2))
      let endMonth = parseInt(editModel.end.floorDayMonth.substr(3, 2))
      if (startMonth > endMonth) {
        errors.push('Month in Start of interval "Day from" must be smaller than or equal to month in End of interval "Day from".')
      } else if (startMonth === endMonth) {
        let startDay = parseInt(editModel.start.floorDayMonth.substr(0, 2))
        let endDay = parseInt(editModel.end.floorDayMonth.substr(0, 2))
        if (startDay > endDay) {
          errors.push('Day in Start of interval "Day from" must be smaller than or equal to day in End of interval "Day from".')
        }
      }
    }
  }
  return errors
}

function validateIntervalCeilingYear(value, field, model) {
  let errors = []
  if (editModel.isInterval) {
    if (isNaN(model.ceilingYear)) {
      return errors
    }
    if (field === startSchema.fields.ceilingYear && editModel.start.ceilingYear == null && editModel.end.ceilingYear != null) {
      errors.push('Start of interval "Year to" must be set if End of interval "Year to" is set.')
    }
    if (editModel.start.ceilingYear != null && editModel.end.ceilingYear != null && editModel.start.ceilingYear > editModel.end.ceilingYear) {
      errors.push('Start of interval "Year to" must be smaller than or equal to End of interval "Year to".')
    }
  }
  return errors
}

function validateIntervalCeilingDayMonth(value, field, model) {
  let errors = []
  if (editModel.isInterval) {
    if (model.ceilingDayMonth === '') {
      return errors
    }
    if (editModel.start.ceilingYear != null && editModel.start.ceilingYear === editModel.end.ceilingYear && editModel.start.ceilingDayMonth != null && editModel.end.ceilingDayMonth != null) {
      let startMonth = parseInt(editModel.start.ceilingDayMonth.substr(3, 2))
      let endMonth = parseInt(editModel.end.ceilingDayMonth.substr(3, 2))
      if (startMonth > endMonth) {
        errors.push('Month in Start of interval "Day to" must be smaller than or equal to month in End of interval "Day to".')
      } else if (startMonth === endMonth) {
        let startDay = parseInt(editModel.start.ceilingDayMonth.substr(0, 2))
        let endDay = parseInt(editModel.end.ceilingDayMonth.substr(0, 2))
        if (startDay > endDay) {
          errors.push('Day in Start of interval "Day to" must be larger than or equal to day in "Day from".')
        }
      }
    }
  }
  return errors
}

function setFloorDayMonth(form) {
  editModel[form].floorDayMonth = '01/01'
  if (form === 'start') {
    startFormRef.value?.validate()
  } else {
    endFormRef.value?.validate()
  }
}

function setCeilingYear(form) {
  editModel[form].ceilingYear = editModel[form].floorYear
  if (form === 'start') {
    startFormRef.value?.validate()
  } else {
    endFormRef.value?.validate()
  }
}

function setCeilingDayMonth(form) {
  editModel[form].ceilingDayMonth = '31/12'
  if (form === 'start') {
    startFormRef.value?.validate()
  } else {
    endFormRef.value?.validate()
  }
}

function add() {
  Object.assign(editModel, {
    index: null,
    valid: false,
    type: null,
    isInterval: null,
    start: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    },
    end: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    }
  })
  editModal.value = true
}

function update(item, index) {
  Object.assign(editModel, {
    valid: true,
    type: item.type,
    isInterval: item.isInterval,
    index: index,
    start: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    },
    end: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    }
  })
  if (!item.isInterval) {
    editModel.start = getFormDate(item.date)
  } else {
    editModel.start = getFormDate(item.interval.start)
    editModel.end = getFormDate(item.interval.end)
  }
  editModal.value = true
}

function del(item, index) {
  Object.assign(editModel, {
    index: index,
    type: null,
    isInterval: null,
    start: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    },
    end: {
      floorYear: null,
      floorDayMonth: null,
      ceilingYear: null,
      ceilingDayMonth: null
    }
  })
  delModal.value = true
}

function editValidated() {
  nextTick(() => {
    if (window.$) {
      window.$.each(['floor-day-month', 'ceiling-year', 'ceiling-day-month'], (i, v) => {
        window.$('.auto-' + v).each(function() {
          window.$(this).css('top', window.$(this).closest('.col-sm-1').prev().find('.' + v).position().top + window.$(this).closest('.col-sm-1').prev().find('.' + v).height() + 15 + 'px')
        })
      })
    }
  })
  editModel.valid = (
      editModel.type != null
      && typeFormRef.value?.errors.length === 0
      && startFormRef.value?.errors.length === 0
      && (
          endFormRef.value == null
          || endFormRef.value.errors.length === 0
      )
  )
}

function submitEdit() {
  if (editModel.index == null) {
    if (!editModel.isInterval) {
      props.model.push({
        date: getTableDate(editModel.start),
        isInterval: editModel.isInterval,
        type: editModel.type
      })
    } else {
      props.model.push({
        interval: {
          start: getTableDate(editModel.start),
          end: getTableDate(editModel.end)
        },
        isInterval: editModel.isInterval,
        type: editModel.type
      })
    }
  } else {
    if (!editModel.isInterval) {
      delete props.model[editModel.index].interval
      props.model[editModel.index].date = getTableDate(editModel.start)
      props.model[editModel.index].isInterval = editModel.isInterval
      props.model[editModel.index].type = editModel.type
    } else {
      delete props.model[editModel.index].date
      props.model[editModel.index].interval = {
        start: getTableDate(editModel.start),
        end: getTableDate(editModel.end)
      }
      props.model[editModel.index].isInterval = editModel.isInterval
      props.model[editModel.index].type = editModel.type
    }
  }
  recalculateTypeValues()
  validateTotal()
  calcChanges()
  emit('validated', isValid.value, null)
  editModal.value = false
}

function submitDelete() {
  props.model.splice(editModel.index, 1)
  recalculateTypeValues()
  validateTotal()
  calcChanges()
  emit('validated', isValid.value, null)
  delModal.value = false
}

function validateTotal() {
  for (let i = 0; i < props.model.length; i++) {
    props.model[i].index = i
  }
  let born = props.model.filter((item) => item.type === 'born')
  let died = props.model.filter((item) => item.type === 'died')
  let attested = props.model.filter((item) => item.type === 'attested')
  let verifyArray = []
  let errorSet = new Set()
  if (born.length === 1 && died.length === 1) {
    verifyArray.push([born[0], died[0]])
  }
  if (born.length === 1) {
    for (let att of attested) {
      verifyArray.push([born[0], att])
    }
  }
  if (died.length === 1) {
    for (let att of attested) {
      verifyArray.push([att, died[0]])
    }
  }
  for (let verify of verifyArray) {
    let first = splitDateOrInterval(verify[0])
    let second = splitDateOrInterval(verify[1])
    if ('floor' in first && 'floor' in second) {
      let floorCeilArray = ['floor', 'ceiling']
      if (first.type === 'attested') {
        floorCeilArray = ['ceiling']
      } else if (second.type === 'attested') {
        floorCeilArray = ['floor']
      }
      for (let floorCeil of floorCeilArray) {
        if (first[floorCeil] == null || second[floorCeil] == null) {
          continue
        }
        if (first[floorCeil].year > second[floorCeil].year) {
          errorSet.add(verify[0].index)
          errorSet.add(verify[1].index)
        } else if (first[floorCeil].year === second[floorCeil].year) {
          if (first[floorCeil].month > second[floorCeil].month) {
            errorSet.add(verify[0].index)
            errorSet.add(verify[1].index)
          } else if (first[floorCeil].month === second[floorCeil].month) {
            if (first[floorCeil].day > second[floorCeil].day) {
              errorSet.add(verify[0].index)
              errorSet.add(verify[1].index)
            }
          }
        }
      }
    }
    else if ('floor' in first && !('floor' in second)) {
      let floorCeilArray = ['floor', 'ceiling']
      if (first.type === 'attested') {
        floorCeilArray = ['ceiling']
      } else if (second.type === 'attested') {
        floorCeilArray = ['floor']
      }
      for (let floorCeil of floorCeilArray) {
        if (first[floorCeil] == null || second.start[floorCeil] == null) {
          continue
        }
        if (first[floorCeil].year > second.start[floorCeil].year) {
          errorSet.add(verify[0].index)
          errorSet.add(verify[1].index)
        } else if (first[floorCeil].year === second.start[floorCeil].year) {
          if (first[floorCeil].month > second.start[floorCeil].month) {
            errorSet.add(verify[0].index)
            errorSet.add(verify[1].index)
          } else if (first[floorCeil].month === second.start[floorCeil].month) {
            if (first[floorCeil].day > second.start[floorCeil].day) {
              errorSet.add(verify[0].index)
              errorSet.add(verify[1].index)
            }
          }
        }
      }
    }
    else if (!('floor' in first) && 'floor' in second) {
      let floorCeilArray = ['floor', 'ceiling']
      if (first.type === 'attested') {
        floorCeilArray = ['ceiling']
      } else if (second.type === 'attested') {
        floorCeilArray = ['floor']
      }
      for (let floorCeil of floorCeilArray) {
        if (first.end[floorCeil] == null || second[floorCeil] == null) {
          continue
        }
        if (first.end[floorCeil].year > second[floorCeil].year) {
          errorSet.add(verify[0].index)
          errorSet.add(verify[1].index)
        } else if (first.end[floorCeil].year === second[floorCeil].year) {
          if (first.end[floorCeil].month > second[floorCeil].month) {
            errorSet.add(verify[0].index)
            errorSet.add(verify[1].index)
          } else if (first.end[floorCeil].month === second[floorCeil].month) {
            if (first.end[floorCeil].day > second[floorCeil].day) {
              errorSet.add(verify[0].index)
              errorSet.add(verify[1].index)
            }
          }
        }
      }
    }
  }
  isValid.value = errorSet.size === 0
  errorArray.value = Array.from(errorSet)
}

function splitDateOrInterval(input) {
  let yearLength = {}
  if (!input.isInterval) {
    for (let floorCeil of ['floor', 'ceiling']) {
      let date = input.date[floorCeil]
      yearLength[floorCeil] = date.replace(/[^-]/g, "").length === 2 ? date.indexOf('-') : date.indexOf('-', 1)
    }
    return {
      floor: input.date.floor == null ? null : {
        year: parseInt(input.date.floor.substr(0, yearLength.floor)),
        month: parseInt(input.date.floor.substr(yearLength.floor + 1, 2)),
        day: parseInt(input.date.floor.substr(yearLength.floor + 4, 2))
      },
      ceiling: input.date.ceiling == null ? null : {
        year: parseInt(input.date.ceiling.substr(0, yearLength.ceiling)),
        month: parseInt(input.date.ceiling.substr(yearLength.ceiling + 1, 2)),
        day: parseInt(input.date.ceiling.substr(yearLength.ceiling + 4, 2))
      },
      type: input.type
    }
  } else {
    for (let startEnd of ['start', 'end']) {
      yearLength[startEnd] = {}
      for (let floorCeil of ['floor', 'ceiling']) {
        let date = input.interval[startEnd][floorCeil]
        yearLength[startEnd][floorCeil] = date.replace(/[^-]/g, "").length === 2 ? date.indexOf('-') : date.indexOf('-', 1)
      }
    }
    return {
      start: {
        floor: input.interval.start.floor == null ? null : {
          year: parseInt(input.interval.start.floor.substr(0, yearLength.start.floor)),
          month: parseInt(input.interval.start.floor.substr(yearLength.start.floor + 1, 2)),
          day: parseInt(input.interval.start.floor.substr(yearLength.start.floor + 4, 2))
        },
        ceiling: input.interval.start.ceiling == null ? null : {
          year: parseInt(input.interval.start.ceiling.substr(0, yearLength.start.ceiling)),
          month: parseInt(input.interval.start.ceiling.substr(yearLength.start.ceiling + 1, 2)),
          day: parseInt(input.interval.start.ceiling.substr(yearLength.start.ceiling + 4, 2))
        }
      },
      end: {
        floor: input.interval.end.floor == null ? null : {
          year: parseInt(input.interval.end.floor.substr(0, yearLength.end.floor)),
          month: parseInt(input.interval.end.floor.substr(yearLength.end.floor + 1, 2)),
          day: parseInt(input.interval.end.floor.substr(yearLength.end.floor + 4, 2))
        },
        ceiling: input.interval.end.ceiling == null ? null : {
          year: parseInt(input.interval.end.ceiling.substr(0, yearLength.end.ceiling)),
          month: parseInt(input.interval.end.ceiling.substr(yearLength.end.ceiling + 1, 2)),
          day: parseInt(input.interval.end.ceiling.substr(yearLength.end.ceiling + 4, 2))
        }
      },
      type: input.type
    }
  }
}

defineExpose({
  init,
  validate,
  reload,
  disableFields,
  enableFields,
  validated,
  isValid,
  changes
})
</script>