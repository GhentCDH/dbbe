<template>
  <nav aria-label="Page navigation">
    <ul class="pagination m-0 user-select-none">
      <!-- First page button -->
      <li v-if="showFirst" class="page-item" :class="{ disabled: currentPage === 1 }">
        <a class="page-link" href="#" @click.prevent="changePage(1)">
          {{ labelFirst || '«' }}
        </a>
      </li>

      <!-- Previous page button -->
      <li class="page-item" :class="{ disabled: currentPage === 1 }">
        <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">
          {{ labelPrevious || '‹' }}
        </a>
      </li>

      <!-- Page numbers -->
      <li
          v-for="page in visiblePages"
          :key="page"
          class="page-item"
          :class="{ active: currentPage === page }"
      >
        <a class="page-link" href="#" @click.prevent="changePage(page)">
          {{ page }}
        </a>
      </li>

      <!-- Next page button -->
      <li class="page-item" :class="{ disabled: currentPage === totalPages }">
        <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">
          {{ labelNext || '›' }}
        </a>
      </li>

      <!-- Last page button -->
      <li v-if="showLast" class="page-item" :class="{ disabled: currentPage === totalPages }">
        <a class="page-link" href="#" @click.prevent="changePage(totalPages)">
          {{ labelLast || '»' }}
        </a>
      </li>
    </ul>
  </nav>
</template>

<script>
export default {
  name: 'BPagination',
  props: {
    totalRecords: {
      type: Number,
      required: true
    },
    page: {
      type: Number,
      required: true
    },
    perPage: {
      type: Number,
      required: true
    },
    maxVisiblePages: {
      type: Number,
      default: 10 // Changed from 5 to 10
    },
    showFirst: {
      type: Boolean,
      default: true
    },
    showLast: {
      type: Boolean,
      default: true
    },
    labelFirst: {
      type: String,
      default: null
    },
    labelPrevious: {
      type: String,
      default: null
    },
    labelNext: {
      type: String,
      default: null
    },
    labelLast: {
      type: String,
      default: null
    }
  },
  computed: {
    totalPages() {
      return Math.ceil(this.totalRecords / this.perPage);
    },
    currentPage: {
      get() {
        return this.page;
      },
      set(newPage) {
        this.$emit('update:page', newPage);
      }
    },
    visiblePages() {
      const total = this.totalPages;
      const current = this.currentPage;
      const maxVisible = this.maxVisiblePages;

      // If total pages is less than max visible, show all pages
      if (total <= maxVisible) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }

      // Calculate start and end of visible page range
      let start = Math.max(1, current - Math.floor(maxVisible / 2));
      let end = start + maxVisible - 1;

      // Adjust if end exceeds total pages
      if (end > total) {
        end = total;
        start = Math.max(1, end - maxVisible + 1);
      }

      // Generate array of page numbers
      const pages = [];
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      return pages;
    }
  },
  methods: {
    changePage(page) {
      if (page > 0 && page <= this.totalPages && page !== this.currentPage) {
        this.currentPage = page;
      }
    }
  }
}
</script>

<style scoped>
.pagination {
  display: flex;
  list-style: none;
  padding: 0;
  margin: 0 auto; /* ADD THIS - centers the ul element */
  width: fit-content; /* ADD THIS - makes the ul only as wide as its content */
}

.page-item {
  margin: 0;
}

.page-item.disabled .page-link {
  cursor: not-allowed;
  opacity: 0.5;
  pointer-events: none;
}

.page-item.active .page-link {
  background-color: #007bff;
  color: white;
  border-color: #007bff;
}

.page-link {
  padding: 0.5rem 0.75rem;
  margin: 0;
  border: 1px solid #dee2e6;
  border-right: none;
  text-decoration: none;
  color: #007bff;
  cursor: pointer;
  display: block;
}

.page-item:last-child .page-link {
  border-right: 1px solid #dee2e6;
}

.page-link:hover:not(.disabled) {
  background-color: #e9ecef;
  z-index: 2;
}

.page-link:focus {
  box-shadow: none;
  outline: none;
  z-index: 3;
}

.page-item.active .page-link {
  z-index: 1;
}
</style>