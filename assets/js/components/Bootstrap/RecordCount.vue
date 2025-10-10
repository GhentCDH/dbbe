<template>
  <div v-if="totalRecords" style="text-align: center;">{{ displayMessage }}</div>
</template>

<script>
export default {
  name: 'RecordCount',
  props: {
    totalRecords: {
      type: Number,
      required: true,
    },
    perPage: {
      type: Number,
      required: true,
    },
    page: {
      type: Number,
      required: true,
    },
    message: {
      type: String,
      default: null,
    }
  },
  computed: {
    totalPages() {
      return Math.ceil(this.totalRecords / this.perPage);
    },
    from() {
      return ((this.page - 1) * this.perPage) + 1;
    },
    to() {
      return this.page === this.totalPages ? this.totalRecords : this.from + this.perPage - 1;
    },
    displayMessage() {
      if (this.message) {
        // If custom message provided, replace placeholders
        return this.message
            .replace('{from}', this.from)
            .replace('{to}', this.to)
            .replace('{count}', this.totalRecords);
      }
      // Default English message
      return `Showing records ${this.from} to ${this.to} out of ${this.totalRecords}`;
    }
  }
};
</script>