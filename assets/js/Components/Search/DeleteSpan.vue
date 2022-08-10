<template>
    <div class="delete-span-box">
        <div v-if="Array.isArray(value)">
            <div v-if="value.length">
                <h4>{{ label }}</h4>
                <div
                    v-for="(val, ind) in value"
                    :key="val.id"
                    class="delete-span-container"
                >
                    {{ val.name }}
                    <i
                        class="fa fa-close delete-span-icon"
                        @click="onDelete(ind)"
                    />
                </div>
            </div>
        </div>
        <div v-else-if="typeof value === 'string' || modelKey === 'year_from' || modelKey === 'year_to'">
            <div v-if="value !== ''">
                <h4>{{ label }}</h4>
                <div class="delete-span-container">
                    {{ value }}
                    <i
                        class="fa fa-close delete-span-icon"
                        @click="onDelete(-1)"
                    />
                </div>
            </div>
        </div>
        <div v-else>
            <h5>{{ label }}</h5>
            <div class="delete-span-container">
                {{ value.name }}
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