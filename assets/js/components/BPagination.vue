<template>
  <nav aria-label="Page navigation">
    <ul class="pagination m-0 user-select-none">
      <li v-if="showFirst" class="page-item" :class="{ disabled: currentPage === 1 }">
        <a class="page-link box-shadow-none" href="#" @click.prevent="changePage(1)">&lt;&lt;</a>
      </li>
      <li class="page-item" :class="{ disabled: currentPage === 1 }">
        <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">&lt;</a>
      </li>
      <li class="page-item" v-for="page in visiblePages" :key="page" :class="{ active: currentPage === page }">
        <a class="page-link" href="#" @click.prevent="changePage(page)">{{ page }}</a>
      </li>
      <li class="page-item" :class="{ disabled: currentPage === totalPages }">
        <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">&gt;</a>
      </li>
      <li v-if="showLast && endPage < totalPages" class="page-item">
        <a class="page-link" href="#" @click.prevent="changePage(totalPages)">&gt;&gt;</a>
      </li>
    </ul>
  </nav>
</template>

<script>
export default {
  name: 'BNavigation',
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
      default: 10
    },
    showFirst: {
      type: Boolean,
      default: true
    },
    showLast: {
      type: Boolean,
      default: true
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
      const chunk = this.maxVisiblePages;
      const currentChunk = Math.ceil(this.currentPage / chunk);
      const start = (currentChunk - 1) * chunk + 1;
      const end = Math.min(this.totalPages, currentChunk * chunk);

      const pages = [];
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      return pages;
    },
    startPage() {
      return this.visiblePages[0];
    },
    endPage() {
      return this.visiblePages[this.visiblePages.length - 1];
    }
  },
  methods: {
    changePage(page) {
      if (page > 0 && page <= this.totalPages) {
        this.currentPage = page;
      }
    }
  }
}
</script>

<style lang="scss">
.page-link:focus {
  box-shadow: none;
}
</style>