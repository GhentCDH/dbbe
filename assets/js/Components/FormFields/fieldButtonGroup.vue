<template>
    <div
        class="button-group-container"
    >
        <button
            v-for="(item, index) in items"
            :key="index"
            type="button"
            class="btn button-group-button"
            @click="onSelection(item)"
        >
            {{ item.name }}
        </button>
    </div>
</template>

<script>
import { abstractField } from 'vue-form-generator';

export default {
    mixins: [abstractField],

    computed: {
        items() {
            const { values } = this.schema;
            return values;
        },
        id() {
            return this.schema.model;
        },
    },

    methods: {
        onSelection(item) {
            this.value = item.value;
        },
    },
};
</script>
