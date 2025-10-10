<template>
  <modal
      :model-value="show"
      size="lg"
      auto-focus
      @input="$emit('cancel')"
  >
    <alerts
        :alerts="alerts"
        @dismiss="$emit('dismiss-alert', $event)"
    />

    <!-- Dependencies -->
    <div v-if="Object.keys(delDependencies).length !== 0">
      <p>
        This {{ submitModel.submitType }} has following dependencies that need to be resolved first:
      </p>
      <template v-for="(dependencyCategory, key) in delDependencies" :key="key">
        <em>{{ key }}</em>
        <ul>
          <li
              v-for="dependency in dependencyCategory.list"
              :key="dependency.id"
              :class="{ greek: ['Occurrences', 'Types'].includes(key) }"
          >
            <a
                v-if="dependencyCategory.url"
                :href="dependencyCategory.url.replace(dependencyCategory.urlIdentifier, dependency.id)"
            >
              {{ dependency.name }}
            </a>
            <template v-else>
              {{ dependency.name }}
            </template>
          </li>
        </ul>
      </template>
    </div>

    <!-- Confirm delete message -->
    <div v-else-if="submitModel[submitModel.submitType] != null">
      <p>
        Are you sure you want to delete {{ formatType(submitModel.submitType) }}
        "<span :class="{ greek: ['occurrence', 'type'].includes(submitModel.submitType) }">
          {{ submitModel[submitModel.submitType].name }}
        </span>"?
      </p>
    </div>

    <!-- Header slot -->
    <template #header>
      <h4
          v-if="submitModel[submitModel.submitType] != null"
          class="modal-title"
      >
        Delete {{ formatType(submitModel.submitType) }}
        "{{ submitModel[submitModel.submitType].name }}"
      </h4>
    </template>

    <!-- Footer slot -->
    <template #footer>
      <btn :disabled="cancelDisabled" @click.native="cancelClick">
        Cancel
      </btn>
      <btn
          type="danger"
          :disabled="Object.keys(delDependencies).length !== 0 || confirmDisabled"
          @click.native="confirmClick"
      >
        Delete
      </btn>
    </template>
  </modal>
</template>

<script setup>
import { ref } from 'vue'
import Alerts from '@/components/Alerts.vue'
import {Modal as modal} from "uiv";

// Props
const props = defineProps({
  show: { type: Boolean, default: false },
  delDependencies: { type: Object, default: () => ({}) },
  submitModel: { type: Object, default: () => ({}) },
  formatType: { type: Function, default: (type) => type },
  alerts: { type: Array, default: () => [] }
})

// Emits
const emit = defineEmits(['cancel', 'confirm', 'dismiss-alert'])

// Reactive state for buttons
const cancelDisabled = ref(false)
const confirmDisabled = ref(false)

// Methods
function cancelClick() {
  cancelDisabled.value = true
  setTimeout(() => {
    cancelDisabled.value = false
  }, 1000)
  emit('cancel')
}

function confirmClick() {
  confirmDisabled.value = true
  setTimeout(() => {
    confirmDisabled.value = false
  }, 1000)
  emit('confirm')
}
</script>
