<template>
    <div class="panel panel-default">
        <div class="panel-heading">
            {{ header }}
            <div class="pull-right">
                <span
                    v-for="(item, index) in links"
                    :key="item.title"
                >
                    {{ item.title }}
                    <a
                        v-if="item.reload"
                        href="#"
                        class="action"
                        :class="{'link-disabled': reloads.includes(item.reload)}"
                        @click.prevent="$emit('reload', item.reload)"
                    >
                        <i class="fa fa-refresh" />
                    </a>
                    <a
                        v-if="item.edit"
                        :href="item.edit"
                        class="action"
                        target="_blank"
                    >
                        <i class="fa fa-edit" />
                    </a>
                    <template v-if="index !== links.length - 1">&nbsp;</template>
                </span>
            </div>
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
        reloads: {
            type: Array,
            default: () => {return []},
        },
    }
}
</script>
