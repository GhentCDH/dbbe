<template>
  <panel :header="header">
    <vue-form-generator
        ref="form"
        :schema="schema"
        :model="model"
        :options="formOptions"
        @validated="validated"
    />
  </panel>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Panel from '../Panel.vue'
import validatorUtil from "@/helpers/validatorUtil"
import { disableFields, enableFields } from "@/helpers/formFieldUtils"

const props = defineProps({
  header: {
    type: String,
    default: '',
  },
  links: {
    type: Array,
    default: () => [],
  },
  model: {
    type: Object,
    default: () => ({}),
  },
  reloads: {
    type: Array,
    default: () => [],
  },
  values: {
    type: Object,
    default: () => ({})
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['validated', 'reload'])

const form = ref(null)
const isValid = ref(true)
const originalModel = ref({})
const changes = ref([])

const schema = ref({
  fields: {
    numberOfVerses: {
      type: 'input',
      inputType: 'number',
      label: 'Number of verses',
      labelClasses: 'control-label',
      model: 'numberOfVerses',
      validator: validatorUtil.number,
      hint: 'Should be left blank if equal to the number of verses listed below. A "0" (without quotes) should be input when the number of verses is unknown.',
    },
    verses: {
      type: 'textArea',
      label: 'Verses',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'verses',
      rows: 12,
      required: true,
      validator: validatorUtil.string,
    },
  }
})

const formOptions = ref({
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
})

const fields = computed(() => schema.value.fields)

// Watch for numberOfVerses changes
watch(() => props.model.numberOfVerses, (newValue) => {
  if (Number.isNaN(newValue)) {
    props.model.numberOfVerses = null
    nextTick(() => {
      validate()
    })
  }
})

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  enableFieldsFunc()
}

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

const disableFieldsFunc = (disableKeys) => {
  disableFields(props.keys, fields.value, disableKeys)
}

const enableFieldsFunc = (enableKeys) => {
  enableFields(props.keys, fields.value, props.values, enableKeys)
}

const validate = () => {
  form.value?.validate()
}

const calcChanges = () => {
  changes.value = []
  for (const key of Object.keys(props.model)) {
    if (
        JSON.stringify(props.model[key]) !== JSON.stringify(originalModel.value[key]) &&
        !(props.model[key] == null && originalModel.value[key] == null)
    ) {
      if (key === 'verses') {
        changes.value.push({
          key: key,
          label: fields.value[key].label,
          old: displayVerses(originalModel.value[key]),
          new: displayVerses(props.model[key]),
          value: props.model[key],
        })
      } else {
        changes.value.push({
          key: key,
          label: fields.value[key].label,
          old: originalModel.value[key],
          new: props.model[key],
          value: props.model[key],
        })
      }
    }
  }
}

const validated = (isValidValue, errors) => {
  isValid.value = isValidValue
  calcChanges()
  emit('validated', isValidValue, errors)
}

const displayVerses = (verses) => {
  if (!verses) return []
  return verses.split('\n').map(verse => '<span class="greek">' + verse + '</span>')
}

// Expose methods for parent component access
defineExpose({
  init,
  reload,
  disableFields: disableFieldsFunc,
  enableFields: enableFieldsFunc,
  validate,
  isValid,
  changes,
})
</script>