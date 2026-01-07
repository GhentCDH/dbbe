<template>
  <panel
      :header="header"
      :links="links"
      :reloads="reloads"
      @reload="reload"
  >
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
import { ref, reactive, computed, watch, nextTick } from 'vue'
import Panel from '../Panel.vue'
import { createMultiSelect, disableFields as disableFieldsHelper, enableFields as enableFieldsHelper } from '@/helpers/formFieldUtils'
import validatorUtil from "@/helpers/validatorUtil"
import { calcChanges } from "@/helpers/modelChangeUtil"

const props = defineProps({
  values: {
    type: Object,
    default: () => ({})
  },
  keys: {
    type: Object,
    default: () => ({
      acknowledgements: { field: 'acknowledgements', init: true },
      statuses: { field: 'status', init: true },
    }),
  },
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
})

const emit = defineEmits(['validated', 'reload'])

// Refs
const form = ref(null)
const changes = ref([])
const isValid = ref(true)
const originalModel = ref({})

const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
}

// Schema
const schema = reactive({
  fields: {
    acknowledgements: createMultiSelect(
        'Acknowledgements',
        {
          model: 'acknowledgements',
          values: props.values?.acknowledgements || [],
        },
        {
          multiple: true,
          closeOnSelect: false,
        }
    ),
    statuses: createMultiSelect(
        'Status',
        {
          values: props.values?.statuses || [],
        },
        {}
    ),
    publicComment: {
      type: 'textArea',
      label: 'Public comment',
      labelClasses: 'control-label',
      model: 'publicComment',
      rows: 4,
      validator: validatorUtil.string,
    },
    privateComment: {
      type: 'textArea',
      styleClasses: 'has-warning',
      label: 'Private comment',
      labelClasses: 'control-label',
      model: 'privateComment',
      rows: 4,
      validator: validatorUtil.string,
    },
    illustrated: {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Illustrated',
      labelClasses: 'control-label',
      model: 'illustrated',
    },
    public: {
      type: 'checkbox',
      styleClasses: 'has-error',
      label: 'Public',
      labelClasses: 'control-label',
      model: 'public',
    },
  }
})

// Computed
const fields = computed(() => schema.fields)

// Methods
const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  enableFields()
}

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

const disableFields = (disableKeys) => {
  disableFieldsHelper(props.keys, fields.value, disableKeys)
}

const enableFields = (enableKeys) => {
  enableFieldsHelper(props.keys, fields.value, props.values, enableKeys)
}

const validated = (isValidValue, errors) => {
  isValid.value = isValidValue
  changes.value = calcChanges(props.model, originalModel.value, fields.value)
  emit('validated', isValidValue, errors)
}

const validate = () => {
  form.value?.validate()
}

// Watchers with deep: true for arrays
watch(
    () => props.values?.acknowledgements,
    (newVal) => {
      if (newVal && newVal.length > 0 && schema.fields.acknowledgements) {
        schema.fields.acknowledgements.values = newVal
      }
    },
    { deep: true } // Add deep option for array watching
)

watch(
    () => props.values?.statuses,
    (newVal) => {
      if (newVal && newVal.length > 0 && schema.fields.statuses) {
        schema.fields.statuses.values = newVal
      }
    },
    { deep: true } // Add deep option for array watching
)

// Expose methods for parent component
defineExpose({
  init,
  reload,
  disableFields,
  enableFields,
  validated,
  validate,
  isValid,
  changes,
})
</script>