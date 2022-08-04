<template>
    <div>{{name}} <i class="fa fa-close" @click="onDelete()"></i></div>
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