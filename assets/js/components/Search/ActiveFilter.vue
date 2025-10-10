<template>
    <div class="active-filter-array-container">
        <button
            class="btn btn-sm btn-primary active-filter-item"
            @click.native="onDelete()"
        >
            <span class="active-filter-label">{{ label }} <span
                v-if="mode"
                class="active-filter-label"
            >({{ mode }})</span></span> {{ value }}
            <i
                class="fa fa-close active-filter-icon"
            />
        </button>
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
            type: [Number, String],
            required: true,
        },
        index: {
            type: Number,
            required: true,
        },
        label: {
            default: '',
            type: String,
        },
        type: {
            default: '',
            type: String,
        },
        mode: {
            default: '',
            type: String,
        },
    },
    methods: {
        /**
         * Emit call to delete filter. valueIndex -1 == switch || -2 == string || rest == remove index from array
         * @param {Number} index Remove this index from the array if this.type === 'array'
         */
        onDelete() {
            if (this.type === 'array') {
                this.$emit('deleted', {
                    key: this.modelKey,
                    valueIndex: this.index,
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
