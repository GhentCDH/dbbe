<template>
    <div>
        <alert
            v-for="(item, index) in alerts"
            :key="index"
            :type="item.type"
            dismissible
            @dismissed="$emit('dismiss', index)">
            {{ item.message }}
        </alert>
    </div>
</template>
<script>
export default {
    props: {
        alerts: {
            type: Array,
            default: () => {return []}
        },
    }
}
</script>
