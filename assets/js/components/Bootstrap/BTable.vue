<template>
  <table class="table">
    <thead>
    <tr>
      <slot name="actionsPreRowHeader">
      </slot>
      <th v-for="field in fieldData" :key="field.key" :class="getFieldHeaderClass(field)" @click="changeSort(field)">
        <div class="header-content">
          <span class="heading-label">{{ field.label }}</span>
          <template v-if="field.sortable">
            <i :class="getSortIcon(field.key)" class="sort-icon"></i>
          </template>
        </div>
      </th>
      <slot name="actionsPostRowHeader">
      </slot>
    </tr>
    </thead>
    <tbody>
    <tr v-for="(item, index) in items" :key="item.id">
      <slot name="actionsPreRow" :item="item" :index="index" :row="item">
      </slot>
      <td v-for="field in fields" :key="field.key" :class="field.tdClass">
        <slot :name="field.key" :item="item" :index="index" :row="item">
          {{ fieldValue(item, field.key) }}
        </slot>
      </td>
      <slot name="actionsPostRow" :item="item" :index="index" :row="item">
      </slot>
    </tr>
    </tbody>
  </table>
</template>

<script>
export default {
  name: 'BTable',
  props: {
    items: {
      type: Array,
      required: true,
      default: () => []
    },
    fields: {
      type: Array,
      required: true,
      default: () => []
    },
    sortBy: {
      type: String,
      default: null
    },
    sortAscending: {
      type: Boolean,
      default: true
    },
    sortIcon: {
      type: Object,
      default: () => ({
        base: 'glyphicon',
        up: 'glyphicon-chevron-up',
        down: 'glyphicon-chevron-down',
        is: 'glyphicon-sort'
      })
    }
  },
  computed: {
    fieldData() {
      return this.fields.map(field => {
        return {
          ...field,
          label: field.label ?? field.key,
          sortable: field.sortable ?? false,
          thClass: field.thClass ?? null,
          tdClass: field.tdClass ?? null,
        };
      });
    }
  },
  methods: {
    getFieldHeaderClass(field) {
      return [
        field.thClass,
        field.sortable ? 'sortable' : '',
        this.sortBy === field.key ? ( `${field.key}-sorted-`+ (this.sortAscending ? 'asc' : 'desc') ) : ''
      ].filter(i => i).join(' ');
    },
    getSortIcon(key) {
      if (this.sortBy !== key) return this.sortIcon.base + ' ' + this.sortIcon.is;
      return this.sortIcon.base + ' ' + (this.sortAscending ? this.sortIcon.up : this.sortIcon.down);
    },
    changeSort(field) {
      if (!field.sortable) {
        return;
      }

      if (this.sortBy === field.key) {
        this.$emit('update:sortAscending', !this.sortAscending);
        this.$emit('sort', {sortBy: field.key, sortAscending: !this.sortAscending});
      } else {
        this.$emit('update:sortBy', field.key);
        this.$emit('update:sortAscending', true);
        this.$emit('sort', {sortBy: field.key, sortAscending: true});
      }
    },
    fieldValue(item, key) {
      return item?.[key] ?? '';
    }
  },
};
</script>

<style scoped>
.sortable {
  cursor: pointer;
  user-select: none;
}

.sortable:hover {
  background-color: #f5f5f5;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.heading-label {
  flex: 1;
}

.sort-icon {
  margin-left: auto;
  padding-left: 8px;
}

tbody tr:nth-child(odd) {
  background-color: #ffffff;
}

tbody tr:nth-child(even) {
  background-color: #f9f9f9;
}

tbody tr:hover {
  background-color: #f1f1f1;
}

th.no-wrap,
td.no-wrap {
  white-space: nowrap;
  min-width: 80px;
  width: auto;
}

th:first-child,
td:first-child {
  white-space: nowrap;
  width: 1%;
  text-align: left;
}

.table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
  border: 1px solid #ddd;
}

.table th,
.table td {
  border: 1px solid #ddd;
  padding: 8px;
}
</style>