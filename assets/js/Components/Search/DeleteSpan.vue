<template>
    <div class="delete-span-box">
        <div v-if="Array.isArray(value)">
            <div v-if="value.length">
                <div class="filter-array-values-container">
                    <button
                        v-for="(val, ind) in value"
                        :key="val.id"
                        class="btn btn-sm btn-primary delete-spam-item"
                    >
                        <b>{{ label }}</b> {{ val.name }}
                        <i
                            class="fa fa-close delete-span-icon"
                            @click="onDelete(ind)"
                        />
                    </button>
                </div>
            </div>
        </div>
        <div v-else-if="typeof value === 'string' || modelKey === 'year_from' || modelKey === 'year_to'">
            <div v-if="value !== ''">
                <button class="btn btn-sm btn-primary delete-spam-item">
                    <b>{{ label }}</b> {{ value }}
                    <i
                        class="fa fa-close delete-span-icon"
                        @click="onDelete(-1)"
                    />
                </button>
            </div>
        </div>
        <div v-else>
            <button class="btn btn-sm btn-primary delete-spam-item">
                <b>{{ label }}</b> {{ value.name }}
                <i
                    class="fa fa-close delete-span-icon"
                    @click="onDelete(-1)"
                />
            </button>
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
