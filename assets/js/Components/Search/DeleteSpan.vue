<template>
    <div class="delete-span-box">
        <div v-if="Array.isArray(value)">
            <div v-if="value.length">
                <div class="filter-array-values-container">
                    <div
                        v-for="(val, ind) in value"
                        :key="val.id"
                        class="delete-span-container"
                    >
                        <b>{{ label }}</b> {{ val.name }}
                        <i
                            class="fa fa-close delete-span-icon"
                            @click="onDelete(ind)"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div v-else-if="typeof value === 'string' || modelKey === 'year_from' || modelKey === 'year_to'">
            <div v-if="value !== ''">
                <div class="delete-span-container">
                    <b>{{ label }}</b> {{ value }}
                    <i
                        class="fa fa-close delete-span-icon"
                        @click="onDelete(-1)"
                    />
                </div>
            </div>
        </div>
        <div v-else>
            <div class="delete-span-container">
                <b>{{ label }}</b> {{ value.name }}
                <i
                    class="fa fa-close delete-span-icon"
                    @click="onDelete(-1)"
                />
            </div>
        </div>
    </div>
</template>
<script>
export default {
    props: ['modelKey', 'value', 'label'],
    methods: {
        onDelete(index) {
            this.$emit('deleted', {
                key: this.modelKey,
                valueIndex: index,
            });
        },
    },
};
</script>
