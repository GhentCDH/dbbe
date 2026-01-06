<template>
    <modal
        :model-value="show"
        size="lg"
        auto-focus
        :backdrop="null"
        @update:model-value="$emit('update:show', $event)">
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)" />
        <vue-form-generator
            :schema="schema"
            :model="submitModel"
            :options="formOptions"
            @validated="editFormValidated"
            ref="edit" />
        <slot
            name="extra"
        />
      <template #header>
        <h4
            v-if="submitModel[submitModel.submitType] && submitModel[submitModel.submitType].id"
            class="modal-title">
            Edit {{ formatType(submitModel.submitType) }}
        </h4>
        <h4
            v-else
            class="modal-title">
            Add a new {{ formatType(submitModel.submitType) }}
        </h4>
      </template>
      <template #footer>

        <btn  @click.native="onCancel">Cancel</btn>
            <btn
                :disabled="JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                type="warning"
                @click.native="$emit('reset')">
                Reset
            </btn>
            <btn
                type="success"
                :disabled="invalidEditForm || JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                @click.native="confirm()">
                {{ submitModel[submitModel.submitType] && submitModel[submitModel.submitType].id ? 'Update' : 'Add' }}
            </btn>
      </template>
    </modal>
</template>
<script>
import Alert from "@/components/Alerts.vue";
import { Modal, Btn } from 'uiv';

export default {
  components: {
    alerts: Alert,
    modal: Modal,
    btn: Btn,
  },
    props: {
      show: {
        type: [Boolean, null],
        default: null
      },
        schema: {
            type: Object,
            default: () => {return {}},
        },
        submitModel: {
            type: Object,
            default: () => {return {}},
        },
        originalSubmitModel: {
            type: Object,
            default: () => {return {}},
        },
        formatType: {
            type: Function,
            default: (type) => {return type},
        },
        alerts: {
            type: Array,
            default: () => {return []}
        },
    },
    data() {
        return {
            revalidating: false,
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidEditForm: true,
        }
    },
    methods: {
      onCancel() {
        this.$emit('update:show', false)
        this.$emit('cancel')
      },
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
        confirm() {
            this.$refs.edit.validate()
            if (this.$refs.edit.errors.length === 0) {
                this.$emit('confirm')
            }
        },
        validate() {
            this.$refs.edit.validate();
        },
    }
}
</script>
