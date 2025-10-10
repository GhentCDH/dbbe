<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="(index) => {alerts.splice(index, 1)}"
            />
        </div>
        <article class="col-sm-9 pbottom-large">
            <div class="pbottom-default">
                <btn
                    class="action"
                    @click="edit()"
                >
                    <i class="fa fa-plus" /> Add a new news item or event
                </btn>
            </div>
            <draggable
                v-model="data"
                @change="onChange"
            >
                <transition-group name="draggable">
                    <div
                        v-for="(item, index) in data"
                        :key="item.order"
                        class="panel panel-default draggable-item"
                    >
                        <div
                            class="panel-body"
                            :class="{'bg-warning': !item.public}"
                        >
                            <i class="fa fa-arrows draggable-icon" />
                            <a
                                v-if="item.url"
                                :href="item.url"
                            >
                                {{ item.title }}
                            </a>
                            <a
                                v-else-if="item.id"
                                :href="urls.news_event_get.replace('news_event_id', item.id)"
                            >
                                {{ item.title }}
                            </a>
                            <template v-else>
                                {{ item.title }}
                            </template>
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
                :disabled="JSON.stringify(data) === JSON.stringify(originalData) || saveDisabled"
                @click="save()"
            >
                Save changes
            </btn>
        </article>
        <modal
            v-model="editModal"
            size="lg"
        >
            <vue-form-generator
                ref="form"
                :schema="schema"
                :model="editModel"
                :options="formOptions"
                @validated="validated"
            />
            <div
                class="form-group"
                :class="{'has-error': editorError}"
            >
                <label
                    for="editor"
                    class="control-label"
                >
                    Full text
                </label>
                <vue-ckeditor
                    id="editor"
                    v-model="editModel.text"
                    type="classic"
                    :config="config"
                />
                <div
                    v-if="editorError"
                    class="errors help-block"
                >
                    <span>
                        Exactly one of the fields "Url", "Full text" is required.
                    </span>
                </div>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="invalid || submitDisabled"
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
                    :disabled="submitDelDisabled"
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
import axios from 'axios';

import Vue from 'vue';
import * as uiv from 'uiv'
import VueFormGenerator from 'vue3-form-generator-legacy'
import VueCkeditor from 'vue-ckeditor2'

import Alerts from '../components/Alerts'


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
                        validator: VueFormGenerator.validators.string,
                    },
                    url: {
                        type: 'input',
                        inputType: 'url',
                        label: 'Url',
                        labelClasses: 'control-label',
                        model: 'url',
                        validator: [VueFormGenerator.validators.url, this.urlOrText],
                    },
                    date: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Date',
                        labelClasses: 'control-label',
                        model: 'date',
                        required: true,
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
                    abstract: {
                        type: 'textArea',
                        label: 'Abstract',
                        labelClasses: 'control-label',
                        model: 'abstract',
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
            },
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
            editorError: false,
            editModal: false,
            editModel: {},
            originalEditModel: {},
            delModal: false,
            submitDisabled: false,
            submitDelDisabled: false,
            saveDisabled: false,
        }
    },
    computed: {
        maxOrder: function() {
            return Math.max.apply(Math, this.data.map(function(d) { return d.order; }));
        },
    },
    watch: {
        'editModel.text'() {
            this.$refs.form.validate();
            this.urlOrText();
        }
    },
    methods: {
        validated (isValid) {
            this.invalid = !isValid
        },
        urlOrText() {
            if (
                (this.editModel.url != null && this.editModel.url !== '')
                && (this.editModel.text != null && this.editModel.text !== '')
            ) {
                this.editorError = true;
                return ['Only one of the fields "Url", "Full text" can be used.']
            }
            this.editorError = false;
            return []
        },
        onChange() {
            if (JSON.stringify(this.data) === JSON.stringify(this.originalData)) {
                window.onbeforeunload = function () {}
            } else {
                window.onbeforeunload = function (e) {
                    let dialogText = 'There are unsaved changes.';
                    e.returnValue = dialogText;
                    return dialogText
                }
            }
        },
        edit(index) {
            let model = {};
            if (index != null) {
                model = JSON.parse(JSON.stringify(this.data[index]));
                model.index = index;
            } else {
                model.order = this.maxOrder + 1;
                model.public = true;
            }
            this.editModel = JSON.parse(JSON.stringify(model));
            if (index == null) {
                this.editModel.text = '';
            }
            this.originalEditModel = JSON.parse(JSON.stringify(model));
            this.editModal = true;
        },
        del(index) {
            let model = JSON.parse(JSON.stringify(this.data[index]));
            model.index = index;
            this.editModel = JSON.parse(JSON.stringify(model));
            this.delModal = true
        },
        submit() {
            this.submitDisabled = true
            setTimeout(() => {
                this.submitDisabled = false
            }, 1000)
            this.$refs.form.validate();
            if (this.invalid) {
                return
            }

            let index = this.editModel.index;
            if (index == null) {
                this.data.unshift(JSON.parse(JSON.stringify(this.editModel)))
            } else {
                delete this.editModel.index;
                this.data[index] = JSON.parse(JSON.stringify(this.editModel));
            }
            this.editModal = false;
            this.onChange();
        },
        submitDel() {
            this.submitDelDisabled = true
            setTimeout(() => {
                this.submitDelDisabled = false
            }, 1000)
            this.data.splice(this.editModel.index, 1);
            this.delModal = false
        },
        save() {
            this.saveDisabled = true
            setTimeout(() => {
                this.saveDisabled = false
            }, 1000)
            this.openRequests++;
            axios.put(this.urls['news_events_put'], this.data)
                .then( () => {
                    window.onbeforeunload = function () {};
                    window.location = this.urls['news_events_get']
                })
                .catch( (error) => {
                    console.log(error);
                    this.alerts.push({type: 'error', message: 'Something went wrong while saving the news items and events.'});
                    this.openRequests--
                })
        },
    }
}
</script>
