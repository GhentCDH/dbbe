<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
        </div>
        <article class="col-sm-9 pbottom-large">
            <div class="pbottom-default">
                <btn
                    class="action"
                    @click="edit()"
                >
                    <i class="fa fa-plus"></i> Add a new news item or event
                </btn>
            </div>
            <draggable
                v-model="data"
                @change="onChange"
            >
                <transition-group>
                    <div
                        v-for="(item, index) in data"
                        :key="index"
                        class="panel panel-default draggable-item"
                    >
                        <div
                            class="panel-body"
                            :class="{'bg-warning': !item.public}"
                        >
                            <i class="fa fa-arrows draggable-icon" />
                            <a :href="item.url">{{ item.title }}</a>
                            <template v-if="item.date">
                                ({{ item.date }})
                            </template>
                            <div class="pull-right">
                                <a
                                    href="#"
                                    title="Edit"
                                    class="action"
                                    @click.prevent="edit(index)"
                                >
                                    <i class="fa fa-pencil-square-o" />
                                </a>
                                <a
                                    href="#"
                                    title="Delete"
                                    class="action"
                                    @click.prevent="del(index)"
                                >
                                    <i class="fa fa-trash-o" />
                                </a>
                            </div>
                        </div>
                    </div>
                </transition-group>
            </draggable>
            <btn
                :disabled="JSON.stringify(data) === JSON.stringify(originalData)"
                @click="save()"
            >
                Save changes
            </btn>
        </article>
        <modal v-model="editModal">
            <vue-form-generator
                ref="form"
                :schema="schema"
                :model="editModel"
                :options="formOptions"
                @validated="validated"
            />
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="invalid"
                    @click="submit()"
                >
                    {{ editModel.index != null ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal v-model="delModal">
            <h6>Are you sure you want to delete "{{ editModel.title }}"</h6>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    :disabled="invalid"
                    @click="submitDel()"
                >
                    Delete
                </btn>
            </div>
        </modal>
        <div
            v-if="openRequests"
            class="loading-overlay"
        >
            <div class="spinner" />
        </div>
    </div>
</template>
<script>
window.axios = require('axios')

import Vue from 'vue'
import draggable from 'vuedraggable'
import * as uiv from 'uiv'
import VueFormGenerator from 'vue-form-generator'

import Alerts from '../Components/Alerts'

Vue.use(uiv)
Vue.use(VueFormGenerator)

export default {
    components: {
        'alerts': Alerts,
        'draggable': draggable,
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
            openRequests: 0,
            alerts: [],
            schema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'title',
                        required: true,
                        validator: [VueFormGenerator.validators.string, VueFormGenerator.validators.required],
                    },
                    url: {
                        type: 'input',
                        inputType: 'url',
                        label: 'Url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: [VueFormGenerator.validators.url, VueFormGenerator.validators.required],
                    },
                    date: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Date',
                        labelClasses: 'control-label',
                        model: 'date',
                        validator: VueFormGenerator.validators.regexp,
                        hint: 'Please use the format YYYY-MM-DD',
                        pattern: '^\\d{4}-([0]\\d|1[0-2])-([0-2]\\d|3[01])$',
                    },
                    public: {
                        type: 'checkbox',
                        label: 'Display publicly',
                        labelClasses: 'control-label',
                        model: 'public',
                    },
                },
            },
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
            },
            invalid: false,
            editModal: false,
            editModel: {},
            originalEditModel: {},
            delModal: false,
        }
    },
    methods: {
        validated (isValid, errors) {
            this.invalid = !isValid
        },
        onChange() {
            if (JSON.stringify(this.data) === JSON.stringify(this.originalData)) {
                window.onbeforeunload = function () {}
            } else {
                window.onbeforeunload = function (e) {
                    let dialogText = 'There are unsaved changes.'
                    e.returnValue = dialogText
                    return dialogText
                }
            }
        },
        edit(index) {
            let model = {}
            if (index != null) {
                model = JSON.parse(JSON.stringify(this.data[index]))
                model.index = index
            }
            this.editModel = JSON.parse(JSON.stringify(model))
            this.originalEditModel = JSON.parse(JSON.stringify(model))
            this.editModal = true
        },
        del(index) {
            let model = JSON.parse(JSON.stringify(this.data[index]))
            model.index = index
            this.editModel = JSON.parse(JSON.stringify(model))
            this.delModal = true
        },
        submit() {
            this.$refs.form.validate()
            if (this.invalid) {
                return
            }

            let index = this.editModel.index
            if (index == null) {
                this.data.unshift(JSON.parse(JSON.stringify(this.editModel)))
            } else {
                delete this.editModel.index
                this.data[index] = JSON.parse(JSON.stringify(this.editModel))
            }
            this.editModal = false
        },
        submitDel() {
            this.data.splice(this.editModel.index, 1)
            this.delModal = false
        },
        save() {
            this.openRequests++
            axios.put(this.urls['news_events_put'], this.data)
                .then( (response) => {
                    window.onbeforeunload = function () {}
                    window.location = this.urls['homepage']
                })
                .catch( (error) => {
                    console.log(error)
                    this.alerts.push({type: 'error', message: 'Something went wrong while saving the news items and events.'})
                    this.openRequests--
                })
        },
    }
}
</script>
