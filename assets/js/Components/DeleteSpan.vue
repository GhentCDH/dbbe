<template>
    <div>{{name}} <i class="fa fa-close delete-span-icon" @click="onDelete()"></i></div>
</template>
<script>
export default {
    props: {
        name: String,
    },
    methods: {
        onDelete() {
            this.$emit('deleted');
        }
    },
}
</script>