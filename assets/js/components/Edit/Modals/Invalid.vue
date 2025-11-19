<template>
    <modal
        :model-value="show"
        title="Invalid fields"
        auto-focus
        @input="$emit('cancel')">
        <p>There are invalid input fields. Please correct them.</p>
      <template #footer>
            <btn
                @click.native="$emit('confirm')"
                data-action="auto-focus">
                OK
            </btn>
      </template>
    </modal>
</template>
<script>
import {Btn,Modal} from "uiv";

export default {
  components: {
    btn: Btn,
    modal: Modal
  },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
    },
}
</script>
