<template>
    <modal
        :value="show"
        :title="'Reset' + title"
        auto-focus
        @input="$emit('cancel')">
        <p>Are you sure you want to reset the {{ title }} information?</p>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                type="danger"
                @click="$emit('confirm')"
                data-action="auto-focus">
                Reset
            </btn>
        </div>
    </modal>
</template>
<script>
export default {
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: '',
        },
    },
}
</script>
