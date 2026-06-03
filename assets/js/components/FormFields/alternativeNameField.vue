<template>
  <div>
    <div
        v-for="(altName, index) in value"
        :key="index"
        class="row mbottom-small"
    >
      <div class="col-sm-5">
        <input
            v-model="altName.firstName"
            type="text"
            class="form-control"
            placeholder="First Name"
            @input="$emit('model-updated', value, schema.model)"
        />
      </div>
      <div class="col-sm-5">
        <input
            v-model="altName.lastName"
            type="text"
            class="form-control"
            placeholder="Last Name"
            @input="$emit('model-updated', value, schema.model)"
        />
      </div>
      <div class="col-sm-2">
        <button type="button" class="btn btn-danger" @click="remove(index)">
          Remove
        </button>
      </div>
    </div>
    <button type="button" class="btn btn-default" @click="add">
      + Add alternative name
    </button>
  </div>
</template>

<script>
import { abstractField } from 'vue-form-generator'

export default {
  mixins: [abstractField],
  methods: {
    add() {
      if (!this.value) {
        this.$set(this.model, this.schema.model, [])
      }
      this.value.push({ firstName: '', lastName: '', alternative: true })
      this.$emit('model-updated', this.value, this.schema.model)
    },
    remove(index) {
      this.value.splice(index, 1)
      this.$emit('model-updated', this.value, this.schema.model)
    },
  }
}
</script>