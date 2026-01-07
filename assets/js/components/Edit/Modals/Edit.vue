<template>
  <modal
      :model-value="show"
      size="lg"
      auto-focus
      :backdrop="null"
      @update:model-value="$emit('update:show', $event)">
    <alerts
        :alerts="alerts"
        @dismiss="$emit('dismiss-alert', $event)" />
    <vue-form-generator
        :schema="schema"
        :model="submitModel"
        :options="formOptions"
        @validated="editFormValidated"
        ref="editFormRef" />
    <slot name="extra" />

    <template #header>
      <h4
          v-if="submitModel[submitModel.submitType]?.id"
          class="modal-title">
        Edit {{ formatType(submitModel.submitType) }}
      </h4>
      <h4
          v-else
          class="modal-title">
        Add a new {{ formatType(submitModel.submitType) }}
      </h4>
    </template>

    <template #footer>
      <btn @click="onCancel">Cancel</btn>
      <btn
          :disabled="!hasChanges"
          type="warning"
          @click="$emit('reset')">
        Reset
      </btn>
      <btn
          type="success"
          :disabled="invalidEditForm || !hasChanges"
          @click="confirm()">
        {{ submitModel[submitModel.submitType]?.id ? 'Update' : 'Add' }}
      </btn>
    </template>
  </modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Alerts from "@/components/Alerts.vue"
import { Modal, Btn } from 'uiv'

const props = defineProps({
  show: {
    type: [Boolean, null],
    default: null
  },
  schema: {
    type: Object,
    default: () => ({})
  },
  submitModel: {
    type: Object,
    default: () => ({})
  },
  originalSubmitModel: {
    type: Object,
    default: () => ({})
  },
  formatType: {
    type: Function,
    default: (type) => type
  },
  alerts: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:show', 'cancel', 'reset', 'confirm', 'dismiss-alert'])

const editFormRef = ref(null)
const invalidEditForm = ref(true)

const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: "has-error",
  validationSuccessClass: "success"
}

const hasChanges = computed(() => {
  return JSON.stringify(props.submitModel) !== JSON.stringify(props.originalSubmitModel)
})

function onCancel() {
  emit('update:show', false)
  emit('cancel')
}

function editFormValidated(isValid, errors) {
  invalidEditForm.value = !isValid
}

function confirm() {
  editFormRef.value?.validate()
  if (editFormRef.value && editFormRef.value.errors.length === 0) {
    emit('confirm')
  }
}

function validate() {
  editFormRef.value?.validate()
}

defineExpose({
  validate
})
</script>