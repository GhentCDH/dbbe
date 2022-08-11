<template>
    <div>
        <button
            v-if="filters.length"
            class="btn btn-sm btn-primary selected-filter-item"
            @click="resetFilters()"
        >
            Reset all filters
            <i
                class="fa fa-close delete-filter-icon"
            />
        </button>
        <active-filter
            v-for="fieldData in filters"
            :key="fieldData.key"
            :model-key="fieldData.key"
            :value="fieldData.value"
            :label="fieldData.label"
            @deleted="deleteField"
        />
    </div>
</template>
<script>
import ActiveFilter from './ActiveFilter.vue';

export default {
    components: { ActiveFilter },
    props: ['filters'],
    methods: {
        deleteField({ key, valueIndex }) {
            this.$emit('deletedField', {
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
