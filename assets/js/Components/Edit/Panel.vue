<template>
    <div class="panel panel-default">
        <div class="panel-heading">
            {{ header }}
            <a
                class="action pull-right"
                v-if="link && link.url && link.text"
                :href="link.url">
                <i class="fa fa-edit" />
                {{ link.text }}
            </a>
        </div>
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
        },
        link: {
            type: Object,
            default: () => {return {}}
        },
    }
}
</script>
