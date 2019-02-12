<template>
    <div class="panel panel-default">
        <div class="panel-heading">
            {{ header }}
            <a
                v-for="(item, index) in links.slice().reverse()"
                :key="index"
                :href="item.url"
                class="edit-link pull-right"
            >
                <i class="fa fa-edit" />
                {{ item.text }}
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
        links: {
            type: Array,
            default: () => {return []}
        },
    }
}
</script>
