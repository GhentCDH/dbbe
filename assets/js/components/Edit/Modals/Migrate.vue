<template>
    <modal
        :value="show"
        size="lg"
        auto-focus
        :backdrop="false"
        @input="$emit('cancel')"
    >
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)"
        />
        <vue-form-generator
            :schema="schema"
            :model="migrateModel"
            :options="formOptions"
            @validated="validated"
        />
        <div slot="header">
            <h4 class="modal-title">
                Migrate {{ formatType(migrateModel.submitType) }} to {{ formatType(migrateModel.toType) }}
            </h4>
        </div>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                :disabled="JSON.stringify(migrateModel) === JSON.stringify(originalMigrateModel)"
                type="warning"
                @click="$emit('reset')"
            >
                Reset
            </btn>
            <btn
                type="success"
                :disabled="invalidForm"
                @click="$emit('confirm')"
            >
                Migrate
            </btn>
        </div>
    </modal>
</template>
<script>
import Vue from 'vue';
import Alert from "@/components/Alerts.vue";
import * as uiv from 'uiv';
import VueFormGenerator from 'vue-form-generator'

Vue.use(uiv);
Vue.use(VueFormGenerator);


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
        migrateModel: {
            type: Object,
            default: () => {return {}},
        },
        originalMigrateModel: {
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
            invalidForm: true,
        }
    },
    methods: {
        validated(isValid, errors) {
            this.invalidForm = !(
                isValid
                && this.migrateModel.primary
                && this.migrateModel.secondary
            )
        },
    }
}
</script>
