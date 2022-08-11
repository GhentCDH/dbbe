<template>
    <div v-if="Array.isArray(value)">
        <div v-if="value.length">
            <div class="active-filter-array-container">
                <button
                    v-for="(val, ind) in value"
                    :key="val.id"
                    class="btn btn-sm btn-primary active-filter-item"
                    @click="onDelete(ind)"
                >
                    <b>{{ label }}</b> {{ val.name }}
                    <i
                        class="fa fa-close active-filter-icon"
                    />
                </button>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    props: {
        modelKey: {
            default: '',
            type: String,
        },
        value: {
            default: () => [],
            type: Array,
        },
        label: {
            default: '',
            type: String,
        },
        type: {
            default: '',
            type: String,
        },
    },
    methods: {
        /**
         * Emit call to delete filter. valueIndex -1 == switch || -2 == string || rest == remove index from array
         * @param {Number} index Remove this index from the array if this.type === 'array'
         */
        onDelete(index) {
            if (this.type === 'array') {
                this.$emit('deleted', {
                    key: this.modelKey,
                    valueIndex: index,
                });
            } else if (this.type === 'switch') {
                this.$emit('deleted', {
                    key: this.modelKey,
                    valueIndex: -1,
                });
            } else {
                this.$emit('deleted', {
                    key: this.modelKey,
                    valueIndex: -2,
                });
            }
        },
    },
};
</script>
