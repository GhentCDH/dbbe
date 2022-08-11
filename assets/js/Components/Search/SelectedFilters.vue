<template>
    <div>
        <delete-span
            v-for="fieldData in filters"
            :key="fieldData.key"
            :model-key="fieldData.key"
            :value="fieldData.value"
            :label="fieldData.label"
            @deleted="deleteField"
        />
        <button
            v-if="filters.length"
            class="btn btn-sm btn-primary delete-spam-item"
            @click="resetFilters()"
        >
            Reset all filters
            <i
                class="fa fa-close delete-span-icon"
            />
        </button>
    </div>
</template>
<script>

import DeleteSpan from './DeleteSpan.vue';

export default {
    components: { DeleteSpan },
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
