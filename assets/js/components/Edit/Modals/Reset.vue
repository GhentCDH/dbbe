<template>
    <modal
        :model-value="show"
        :title="'Reset' + title"
        auto-focus
        @input="$emit('cancel')">
        <p>Are you sure you want to reset the {{ title }} information?</p>
      <template #footer>
        <btn @click.native="$emit('cancel')">Cancel</btn>
            <btn
                type="danger"
                @click.native="$emit('confirm')"
                data-action="auto-focus">
                Reset
            </btn>
        </template>
    </modal>
</template>
<script>


import {Btn, Modal} from "uiv";

export default {
    components:{
      modal: Modal,
      btn: Btn
    },
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
