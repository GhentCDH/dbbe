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
            :mode="fieldData.mode"
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
                    const constructedValue = JSON.parse(JSON.stringify(fieldData));
                    constructedValue.name = fieldValue.name;
                    if (fieldData.type === 'array') {
                        constructedValue.index = counter;
                    } else {
                        constructedValue.index = -1;
                    }
                    allFilters.push(constructedValue);
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
