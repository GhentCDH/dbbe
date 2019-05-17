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
        <div slot="header">
            <h4 class="modal-title">
                Merge {{ formatType(mergeModel.submitType) }}
            </h4>
        </div>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                :disabled="JSON.stringify(mergeModel) === JSON.stringify(originalMergeModel)"
                type="warning"
                @click="$emit('reset')">
                Reset
            </btn>
            <btn
                type="success"
                :disabled="invalidMergeForm"
                @click="$emit('confirm')">
                Merge
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
