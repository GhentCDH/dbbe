<template>
  <panel
      :header="header"
      :links="links"
      :reloads="reloads"
      @reload="reload"
  >
    <vue-form-generator
        ref="formRef"
        :schema="schema"
        :model="model"
        :options="formOptions"
        @validated="validated"
    />
  </panel>
</template>

<script setup>
import { ref, computed, watch, onMounted, reactive } from 'vue';
import Panel from '../Panel.vue';
import { calcChanges } from '@/helpers/modelChangeUtil';
import {
  createMultiSelect,
  disableFields as disableFieldsHelper,
  enableFields as enableFieldsHelper,
} from '@/helpers/formFieldUtils';

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
  keys: {
    type: Object,
    default: () => ({
      managements: { field: 'managements', init: true },
    }),
  },
  values: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['validated', 'reload']);

// Refs
const formRef = ref(null);
const changes = ref([]);
const isValid = ref(true);
const originalModel = ref({});

// Form options
const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

// Schema - using reactive to maintain deep reactivity
const schema = reactive({
  fields: {
    managements: createMultiSelect(
        'Management collection',
        {
          model: 'managements',
          values: [],
        },
        {
          multiple: true,
          closeOnSelect: false,
        }
    ),
  },
});

// Computed
const fields = computed(() => schema.fields);

// Methods
const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
};

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
};

const disableFields = (disableKeys) => {
  disableFieldsHelper(props.keys, fields.value, disableKeys);
};

const enableFields = (enableKeys) => {
  enableFieldsHelper(props.keys, fields.value, props.values, enableKeys);
};

const validate = () => {
  formRef.value?.validate();
};

const validated = (valid, errors) => {
  isValid.value = valid;
  changes.value = calcChanges(props.model, originalModel.value, fields.value);
  emit('validated', valid, errors, {
    changes: changes.value,
    isValid: isValid.value,
  });
};

// Watch for values changes
watch(
    () => props.values,
    (newValues) => {
      if (newValues && newValues.length > 0) {
        // Update the values in the schema
        schema.fields.managements.values = newValues;
        enableFields();
      }
    },
    { immediate: true, deep: true }
);

// Initialize on mount
onMounted(() => {
  init();
});

// Expose methods for parent component
defineExpose({
  validate,
  init,
  reload,
  enableFields,
  disableFields,
  changes,
  isValid,
});
</script>