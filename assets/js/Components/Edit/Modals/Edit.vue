<template>
    <modal
        :value="show"
        size="lg"
        auto-focus
        :backdrop="false"
        @input="$emit('cancel')">
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
        <div slot="header">
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
        </div>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                :disabled="JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                type="warning"
                @click="$emit('reset')">
                Reset
            </btn>
            <btn
                type="success"
                :disabled="invalidEditForm || JSON.stringify(submitModel) === JSON.stringify(originalSubmitModel)"
                @click="confirm()">
                {{ submitModel[submitModel.submitType] && submitModel[submitModel.submitType].id ? 'Update' : 'Add' }}
            </btn>
        </div>
    </modal>
</template>
<script>
import Alert from "@/Components/Alerts.vue";

export default {
  components: {
    alerts: Alert
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
    // mounted() {
    //     for (const [field, fieldDef] of Object.entries(this.schema.fields)) {
    //         if (fieldDef.inputType === 'number') {
    //             this.$watch(
    //                 function () {
    //                     return this.submitModel[this.submitModel.submitType][field];
    //                 },
    //                 function () {
    //                     if (Number.isNaN(this.submitModel[this.submitModel.submitType][field])) {
    //                         this.$emit('fix-nan', field);
    //                         this.validate();
    //                     }
    //                 },
    //             );
    //         }
    //     }
    // },
    methods: {
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
