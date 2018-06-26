<template>
    <modal
        :value="show"
        size="lg"
        auto-focus
        @input="$emit('cancel')">
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)" />
        <vue-form-generator
            :schema="schema"
            :model="submitModel"
            :options="formOptions"
            @validated="editFormValidated" />
        <div slot="header">
            <h4
                v-if="submitModel[submitModel.type] && submitModel[submitModel.type].id"
                class="modal-title">
                Edit {{ formatType(submitModel.type) }}
            </h4>
            <h4
                v-else
                class="modal-title">
                Add a new {{ formatType(submitModel.type) }}
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
                @click="$emit('confirm')">
                {{ submitModel[submitModel.type] && submitModel[submitModel.type].id ? 'Update' : 'Add' }}
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
    data () {
        return {
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalidEditForm: true,
        }
    },
    methods: {
        editFormValidated(isValid, errors) {
            this.invalidEditForm = !isValid
        },
    }
}
</script>
