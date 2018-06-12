<template>
    <modal
        v-model="model"
        title="Invalid fields"
        auto-focus>
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
        model: {
            type: Boolean,
            default: false,
        },
    },
}
</script>
