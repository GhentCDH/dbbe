<template>
  <modal
      :value="show || null"
      :title="'Save ' + title"
      size="lg"
      auto-focus
      @update:model-value="$emit('cancel')"
  >
    <alerts
        :alerts="alerts"
        @dismiss="$emit('dismiss-alert', $event)"
    />

    <p>Are you sure you want to save this {{ title }} information?</p>

    <table class="table table-striped table-hover">
      <thead>
      <tr>
        <th class="col-md-2">Field</th>
        <th class="col-md-5">Previous value</th>
        <th class="col-md-5">New value</th>
      </tr>
      </thead>
      <tbody>
      <tr
          v-for="row in diff"
          :key="row.keyGroup == null ? row.key : row.keyGroup + '.' + row.key"
      >
        <td>{{ row.label }}</td>
        <template v-for="key in ['old', 'new']">
          <td
              v-if="Array.isArray(row[key])"
              :key="key + '-array'"
              class="word-break"
          >
            <ul v-if="row[key].length > 0">
              <li
                  v-for="(item, index) in row[key]"
                  :key="index"
                  v-html="getDisplay(item)"
              />
            </ul>
          </td>
          <td
              v-else
              :key="key + '-single'"
              v-html="getDisplay(row[key])"
          />
        </template>
      </tr>
      </tbody>
    </table>

    <template #footer>
      <btn :disabled="cancelDisabled" @click="cancelClick">Cancel</btn>
      <btn
          :disabled="confirmDisabled"
          type="success"
          data-action="auto-focus"
          @click="confirmClick"
      >
        Save
      </btn>
    </template>
  </modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Alerts from '@/components/Alerts.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  diff: { type: Array, default: () => [] },
  alerts: { type: Array, default: () => [] }
})

const emit = defineEmits(['cancel', 'confirm', 'dismiss-alert'])

const cancelDisabled = ref(false)
const confirmDisabled = ref(false)

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

function getDisplay(item) {
  if (item == null) return null
  if (typeof item === 'object' && 'name' in item) return item.name
  if (typeof item === 'string') return item.split('\n').join('<br />')
  return item
}
</script>
