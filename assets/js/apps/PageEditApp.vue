<template>
    <div>
        <alerts
            :alerts="alerts"
            @dismiss="alerts.splice($event, 1)"
        />
        <vue-form-generator
            ref="form"
            :schema="schema"
            :model="data"
            :options="formOptions"
            @validated="validated"
        />
        <vue-ckeditor
            v-model="data.content"
            type="classic"
            :config="config"
        />
        <btn
            :disabled="invalid || (data.title === originalData.title && data.content === originalData.content && data.display_navigation === originalData.display_navigation)"
            @click="submit()"
        >
            Save
        </btn>
        <div
            v-if="openRequests"
            class="loading-overlay"
        >
            <div class="spinner" />
        </div>
    </div>
</template>

<script>
import axios from 'axios'

import Vue from 'vue';
import * as uiv from 'uiv'
import VueFormGenerator from 'vue-form-generator'
import VueCkeditor from 'vue-ckeditor2'

import Alerts from '../components/Alerts'

Vue.use(uiv)
Vue.use(VueFormGenerator)

export default {
    components: {
        'alerts': Alerts,
        'vue-ckeditor': VueCkeditor,
    },
    props: {
        initUrls: {
            type: String,
            default: '',
        },
        initData: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            urls: JSON.parse(this.initUrls),
            data: JSON.parse(this.initData),
            originalData: JSON.parse(this.initData),
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
            },
            openRequests: 0,
            alerts: [],
            config: {
                language: 'en',
                toolbarGroups: [
                    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                    { name: 'styles', groups: [ 'styles' ] },
                    { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
                    { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
                    { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing' ] },
                    { name: 'links', groups: [ 'links' ] },
                    { name: 'insert', groups: [ 'insert' ] },
                    { name: 'forms', groups: [ 'forms' ] },
                    { name: 'tools', groups: [ 'tools' ] },
                    { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
                    { name: 'others', groups: [ 'others' ] },
                    '/',
                    { name: 'colors', groups: [ 'colors' ] },
                ],
                removeButtons: 'Underline,Subscript,Superscript,Scayt,Strike,Styles,Outdent,Indent,Blockquote,About',
                extraAllowedContent: 'iframe[*]; h2[id]',
            },
            invalid: false,
            schema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    display_navigation: {
                        type: 'checkbox',
                        label: 'Display inpage navigation',
                        labelClasses: 'control-label',
                        model: 'display_navigation',
                    },
                },
            },
        }
    },
    watch: {
        'data.title'() {
            this.setExitWarning()
        },
        'data.content'() {
            this.setExitWarning()
        },
    },
    methods: {
        submit () {
            if (this.$refs.form.errors.length !== 0) {
                return
            }
            this.openRequests++
            axios.put(this.urls['page_put'], {
                title: this.data.title,
                content: this.data.content,
                display_navigation: this.data.display_navigation,
            })
                .then( (response) => {
                    window.onbeforeunload = function () {}
                    window.location = this.urls['page_get']
                })
                .catch( (error) => {
                    console.log(error)
                    this.alerts.push({type: 'error', message: 'Something went wrong while saving the page.'})
                    this.openRequests--
                })
        },
        validated (isValid, errors) {
            this.invalid = !isValid
        },
        setExitWarning () {
            if (this.data.title !== this.originalData.title || this.data.content !== this.originalData.content  || this.data.display_navigation !== this.originalData.display_navigation) {
                window.onbeforeunload = function (e) {
                    let dialogText = 'There are unsaved changes.'
                    e.returnValue = dialogText
                    return dialogText
                }
            }
            else {
                window.onbeforeunload = function () {}
            }
        },
    },
}
</script>
