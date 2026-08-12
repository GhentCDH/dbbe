<template>
  <div>
    <alert
        v-for="(item, index) in alerts"
        :key="index"
        :type="item.type"
        dismissible
        @dismissed="$emit('dismiss', index)"
    >
      <p>{{ item.message }}</p>

      <p v-if="item.extra">
        {{ item.extra }}
      </p>

      <p v-if="item.login">
        Is it possible your login timed out? Try
        <btn @click.native="login(index)">
          logging in
        </btn>
        again.
      </p>
    </alert>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, getCurrentInstance } from 'vue'

// Props
const props = defineProps({
  alerts: {
    type: Array,
    default: () => [],
  },
})

// Emits
const emit = defineEmits(['dismiss'])

// Access global properties (if you set login URL globally)
const { proxy } = getCurrentInstance()

// Methods
function login(index) {
  const loginUrl = proxy?.urls?.login || '/login'
  window.open(`${loginUrl}?close=true`)
  emit('dismiss', index)
}
</script>
