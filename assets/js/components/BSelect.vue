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
<span class="select-wrapper">
  <select class="form-select" @change="updateSelected($event)">
    <option v-for="option in options" :key="option.value" :value="option.value"
            :selected="option.value === selected">
      {{ option.text }}
    </option>
  </select>
</span>

</template>

<style lang="scss">
.form-select:focus {
  box-shadow: none;
}

.form-select {
  line-height: 1.5;
  height: calc(1.5em + 0.75rem + 2px); // roughly match Bootstrap pagination button height
  padding: 0.375rem 0.75rem; // match pagination padding
  border-radius: 0.25rem;
  border: 1px solid #dee2e6;
  color: #212529;
  background-color: #fff;

  &:focus {
    outline: none;
    box-shadow: none;
    border-color: #86b7fe; // optional subtle focus
  }
}

.form-select {
  border-radius: 0.25rem; // match Bootstrap pagination buttons
  border: 1px solid #dee2e6;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem; // match pagination font size
  line-height: 1.5;
  background-color: #fff;
  color: #212529;

  &:focus {
    outline: none;
    box-shadow: none; // same as pagination
    border-color: #86b7fe; // subtle focus border if you want
  }

  &:disabled {
    background-color: #e9ecef;
    opacity: 1;
  }
}

// optional: match margin/padding of pagination items
.select-wrapper {
  display: inline-block;
  vertical-align: middle;
  margin: 0 0.25rem;
}

</style>