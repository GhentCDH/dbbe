<template>
    <div class="panel panel-default">
        <div class="panel-heading">{{ header }}</div>
        <div class="panel-body">
            <slot />
        </div>
    </div>
</template>
<script>
export default {
    props: {
        header: {
            type: String,
            default: ''
        }
    }
}
</script>
