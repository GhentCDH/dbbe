<template>
    <div class="panel-group">
        <div class="panel panel-default">
            <div
                class="panel-heading"
                role="button"
                @click.native="toggleAccordion(0)">
                <h4 class="panel-title">
                    <a :aria-expanded="showAccordion[0]">
                        Give feedback
                    </a>
                </h4>
            </div>
            <collapse v-model="showAccordion[0]">
                <div
                    v-if="status == null"
                    class="panel-body">
                    <vue-form-generator
                        :schema="schema"
                        :model="model"
                        :options="formOptions"
                        ref="form"
                        @validated="validated" />
                    <recaptcha
                        ref="recaptcha"
                        :site-key="siteKey"
                        @verify="onVerify" />
                    <btn
                        :disabled="invalid"
                        @click.native="submit()">
                        Submit
                    </btn>
                </div>
                <div
                    v-if="status !=null"
                    class="panel-body">
                    {{ status }}
                </div>
            </collapse>
        </div>
    </div>
</template>

<script>
import axios from 'axios'
import VueFormGenerator from 'vue3-form-generator-legacy'

import Recaptcha from '../components/Recaptcha'

export default {
    components: {
        Recaptcha,
    },
    props: {
        siteKey: {
            type: String,
            default: '',
        },
        feedbackUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            invalid: true,
            model: {
                email: null,
                message: null,
            },
            recaptchaResponse: null,
            schema: {
                fields: {
                    email: {
                        type: 'input',
                        inputType: 'email',
                        label: 'Your email address',
                        labelClasses: 'control-label',
                        model: 'email',
                        required: true,
                        validator: VueFormGenerator.validators.email,
                    },
                    message: {
                        type: 'textArea',
                        rows: 10,
                        label: 'Feedback message',
                        labelClasses: 'control-label',
                        model: 'message',
                        required: true,
                        max: 4000,
                        hint: 'Maximum 4000 characters are allowed',
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            showAccordion: [null],
            status: null,
        }
    },
    methods: {
        onVerify(recaptchaResponse) {
            this.recaptchaResponse = recaptchaResponse
            this.$refs.form.validate()
        },
        submit() {
            if (this.$refs.form.errors.length !== 0) {
                return
            }
            this.status = 'Your feedback is being sent.'
            axios.post(this.feedbackUrl, {
                email: this.model.email,
                message: this.model.message,
                recaptcha: this.recaptchaResponse,
                url: window.location.href,
            })
                .then( (response) => {
                    this.status = 'Your feedback has been sent successfully. Thank you for your input.'
                })
                .catch( (error) => {
                    this.status = 'Something went wrong while sending your feedback. Please contact the team via dbbe@ugent.be'
                })
        },
        toggleAccordion (index) {
            if (this.showAccordion[index]) {
                this.$set(this.showAccordion, index, false)
            } else {
                this.showAccordion = this.showAccordion.map((v, i) => i === index)
            }
        },
        validated (isValid, errors) {
            this.invalid = (
                !isValid
                || this.model.email == null
                || this.model.message == null
                || this.recaptchaResponse == null
            )
        },
    },
}
</script>
