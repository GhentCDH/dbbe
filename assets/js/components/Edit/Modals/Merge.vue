<template>
    <modal
        :model-value="show"
        size="lg"
        auto-focus
        :backdrop="null"
        @input="$emit('cancel')">
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)" />
        <vue-form-generator
            :schema="schema"
            :model="mergeModel"
            :options="formOptions"
            @validated="mergeFormValidated" />
        <div
            v-if="mergeModel.primary && mergeModel.secondary"
            class="panel panel-default">
            <div class="panel-heading">Preview of the merge</div>
            <div class="panel-body">
                <slot name="preview" />
            </div>
        </div>
      <template #header>
            <h4 class="modal-title">
                Merge {{ formatType(mergeModel.submitType) }}
            </h4>
      </template>
      <template #footer>
            <btn @click.native="$emit('cancel')">Cancel</btn>
            <btn
                :disabled="JSON.stringify(mergeModel) === JSON.stringify(originalMergeModel)"
                type="warning"
                @click.native="$emit('reset')">
                Reset
            </btn>
            <btn
                type="success"
                :disabled="invalidMergeForm"
                @click.native="$emit('confirm')">
                Merge
            </btn>
      </template>
    </modal>
</template>
<script>
import Alert from "@/components/Alerts.vue";
import {Btn, Modal} from "uiv";

export default {
  components: {
    alerts: Alert,
    modal: Modal,
    btn: Btn,
  },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        schema: {
            type: Object,
            default: () => {return {}},
        },
        mergeModel: {
            type: Object,
            default: () => {return {}},
        },
        originalMergeModel: {
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
    data () {
        return {
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidMergeForm: true,
        }
    },
    methods: {
        mergeFormValidated(isValid, errors) {
            this.invalidMergeForm = !(isValid && this.mergeModel.primary && this.mergeModel.secondary && this.mergeModel.primary.id != this.mergeModel.secondary.id)
        },
    }
}
</script>
