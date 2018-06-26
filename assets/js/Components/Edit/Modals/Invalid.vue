<template>
    <modal
        :value="show"
        title="Invalid fields"
        auto-focus
        @input="$emit('cancel')">
        <p>There are invalid input fields. Please correct them.</p>
        <div slot="footer">
            <btn
                @click="$emit('confirm')"
                data-action="auto-focus">
                OK
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
    },
}
</script>
