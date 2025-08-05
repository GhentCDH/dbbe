<template>
  <panel
      :header="header"
      :links="links"
      :reloads="reloads"
      @reload="emitReload"
  >
    <vue-form-generator
        ref="form"
        :schema="schema"
        :model="model"
        :options="formOptions"
        @validated="onValidated"
    />
  </panel>
</template>

<script setup>
import { ref, computed, watch, reactive,defineExpose } from 'vue';
import { createMultiSelect, disableFields, enableFields } from '@/helpers/formFieldUtils';
import { calcChanges } from '@/helpers/modelChangeUtil';
import Panel from '../Panel.vue';

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
      managements: { field: 'managements', init: true }
    }),
  },
  values: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['reload', 'validated']);

const schema = ref({ fields: {} });
const changes = ref([]);
const isValid = ref(true);
const originalModel = ref({});
const form = ref(null);

const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

const fields = computed(() => schema.value.fields);

watch(
    () => props.values,
    (newValues) => {
      if (!Array.isArray(newValues) || newValues.length === 0) {
        return;
      }

      schema.value.fields = {
        managements: createMultiSelect(
            'Management collection',
            {
              model: 'managements',
              values: newValues,
            },
            {
              multiple: true,
              closeOnSelect: false,
            }
        ),
      };
    },
    { immediate: true }
);

// Methods
function init() {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
  enableFieldsFn();
}

function emitReload(type) {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
}

function disableFieldsFn(disableKeys) {
  disableFields(props.keys, fields.value, disableKeys);
}

function enableFieldsFn(enableKeys) {
  enableFields(props.keys, fields.value, props.values, enableKeys);
}

function onValidated(valid, errors) {
  isValid.value = valid;
  changes.value = calcChanges(props.model, originalModel.value, fields.value);
  emit('validated', valid, errors, {
    validate,
    changes: changes.value,
    schema: schema.value,
  });
}

function validate() {
  form.value?.validate();
}

defineExpose({
  init,
  changes,
  validate,
  isValid
});
</script>
