<template>
    <div>
        <button
            v-if="filters.length"
            class="btn btn-sm btn-primary active-filter-item"
            @click="resetFilters()"
        >
            Reset all filters
            <i
                class="fa fa-close delete-filter-icon"
            />
        </button>
        <active-filter
            v-for="(fieldData, ind) in flattenFilters"
            :key="ind"
            :model-key="fieldData.key"
            :value="fieldData.name"
            :index="fieldData.index"
            :label="fieldData.label"
            :type="fieldData.type"
            @deleted="deleteActiveFilter"
        />
    </div>
</template>
<script>
import ActiveFilter from './ActiveFilter.vue';

export default {
    components: { ActiveFilter },
    props: {
        filters: {
            default: () => [],
            type: Array,
        },
    },
    computed: {
        flattenFilters() {
            const allFilters = [];
            for (const fieldData of this.filters) {
                let counter = 0;
                for (const fieldValue of fieldData.value) {
                    if (fieldData.type === 'array') {
                        fieldValue.index = counter;
                    } else {
                        fieldValue.index = -1;
                    }
                    fieldValue.key = fieldData.key;
                    fieldValue.type = fieldData.type;
                    fieldValue.label = fieldData.label;
                    allFilters.push(fieldValue);
                    counter += 1;
                }
            }
            return allFilters;
        },
    },
    methods: {
        deleteActiveFilter({ key, valueIndex }) {
            this.$emit('deletedActiveFilter', {
                key,
                valueIndex,
            });
        },
        resetFilters() {
            this.$emit('resetFilters');
        },
    },
};
</script>
