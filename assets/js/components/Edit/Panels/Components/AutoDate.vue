<template>
    <div class="col-sm-1">
        <a
            class="action auto-floor-day-month"
            :style="'position:absolute;top:' + (offset + 135) + 'px;cursor:pointer;'"
            title="Insert 01/01"
            @click.prevent="$emit('set-floor-day-month')"
        >
            <i class="fa fa-magic" />
        </a>
        <a
            v-if="model.floorYear"
            class="action auto-ceiling-year"
            :style="'position:absolute;top:' + (offset + 215) + 'px;cursor:pointer;'"
            title="Copy 'Year from' value"
            @click.prevent="$emit('set-ceiling-year')"
        >
            <i class="fa fa-magic" />
        </a>
        <a
            class="action auto-ceiling-day-month"
            :style="'position:absolute;top:' + (offset + 315) + 'px;cursor:pointer;'"
            title="Insert 31/12"
            @click.prevent="$emit('set-ceiling-day-month')"
        >
            <i class="fa fa-magic" />
        </a>
    </div>
</template>
<script>
export default {
    props: {
        model: {
            type: Object,
            required: true,
        },
        offset: {
            type: Number,
            default: 0,
        }
    },
}
</script>