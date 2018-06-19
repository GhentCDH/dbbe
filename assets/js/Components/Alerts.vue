<template>
    <div>
        <alert
            v-for="(item, index) in alerts"
            :key="index"
            :type="item.type"
            v-html="item.message"
            dismissible
            @dismissed="$emit('dismiss', index)" />
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
