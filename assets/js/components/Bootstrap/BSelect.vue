<script>
export default {
  name: 'BSelect',
  props: {
    id: {
      type: String,
      required: true
    },
    label: {
      type: String,
      required: true
    },
    selected: {
      type: [String, Number],
      required: true
    },
    options: {
      type: Array,
      required: true,
      validator: function (value) {
        return value.every(option => 'value' in option && 'text' in option);
      }
    }
  },
  methods: {
    updateSelected(event) {
      this.$emit('update:selected', event.target.value);
    }
  }
}
</script>

<template>
  <select class="form-select" @change="updateSelected($event)">
    <option v-for="option in options" :key="option.value" :value="option.value"
            :selected="option.value === selected">
      {{ option.text }}
    </option>
  </select>
</template>

<style lang="scss">
.form-select:focus {
  box-shadow: none;
}

.form-select {
  background-color: white;  // Set white background
  border-radius: 0;         // Remove rounded corners
  border: 1px solid #ccc;   // Optional: keep a border to make it visible
  padding: 0.5rem;          // Optional: adjust padding if needed
}

.form-select:focus {
  box-shadow: none;         // Keep focus shadow removed
  outline: none;            // Optional: remove default outline
}

</style>